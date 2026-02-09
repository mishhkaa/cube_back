<?php

namespace App\Jobs\Webhooks;

use App\Facades\Mailchimp;
use App\Facades\PipeDrive;
use App\Models\DataJob;
use App\Models\Enums\JobStatus;
use App\Models\FacebookPixel;
use App\Models\GoogleAdsAccount;
use App\Services\Conversions\FacebookConversionsService;
use App\Services\Conversions\GoogleOfflineConversionsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ProcessCRMWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(protected array $data, protected DataJob $dataJob)
    {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $this->dataJob->setProcessing();

        match ($this->get('meta.action').'.'.$this->get('meta.entity')) {
            'create.deal' => $this->addedDeal(),
            'change.deal' => $this->updatedDeal(),
            'delete.deal' => $this->deletedDeal(),
            default => null
        };

        $this->dataJob->setDone();
    }

    protected function addActionToRequest(string $name = 'pipedrive-bigquery-add'): void
    {
        $this->dataJob->request?->setAction($name)->save();
    }

    protected function addMessageToRequest(string $message): void
    {
        $this->dataJob->request?->setMessage($message)->save();
    }

    private const array STAGES_EVENTS = [
        [118],
        [119],
        [182],
        [183],
        [129],
        [184],
        'lead_qualified' => [164],
        'lead_brief' => [4],
        [123, 124],
        [163],
        [7],
        'lead_payment' => [27],
    ];

    private const array PIPELINES_EVENTS = [1, 15];

    protected function processAnalyticsEvents(): void
    {
        $stageId = $this->get('data.stage_id');
        $prevStageId = $this->get('previous.stage_id', $stageId);

        $status = $this->get('data.status');
        $prevStatus = $this->get('previous.status', $status);

        $pipelineId = $this->get('data.pipeline_id');
        $prevPipelineId = $this->get('previous.pipeline_id', $pipelineId);

        if ($status !== $prevStatus || $prevStageId !== $stageId) {
            $this->addActionToRequest();
        }

        $events = [];

        if ($pipelineId !== $prevPipelineId && $prevPipelineId === 15 && $pipelineId === 18) {
            $events['lead_qualified'] = [];
        }

        if ($status !== $prevStatus && $status === 'won') {
            $events['purchase'] = [
                'currency' => $this->get('data.currency'),
                'value' => $this->get('data.value')
            ];
        }

        $affectedStagesIds = [];
        if (
            $prevStageId !== $stageId
            && in_array($pipelineId, self::PIPELINES_EVENTS, true)
            && in_array($prevPipelineId, self::PIPELINES_EVENTS, true)
        ) {
            $start = false;
            foreach (self::STAGES_EVENTS as $event => $stages) {
                if (in_array($stageId, $stages, true)) {
                    if (!is_numeric($event)) {
                        $events[$event] = [];
                    }
                    break;
                }

                if ($start) {
                    if (!is_numeric($event)) {
                        $events[$event] = [];
                    }
                    if (count($stages) === 1) {
                        $affectedStagesIds = [...$affectedStagesIds, ...$stages];
                    }
                    continue;
                }

                if (in_array($prevStageId, $stages, true)) {
                    $start = true;
                }
            }
        }
        if ($affectedStagesIds) {
            $this->addMessageToRequest(implode(',', $affectedStagesIds));
        }

        if (!$events) {
            return;
        }

        $defaultParams = $this->getGA4Params();
        $sendingEvents = [];
        foreach ($events as $event => $params) {
            if ($event === 'lead_qualified') {
                $this->addEventToMailchimp('lead-qualified');
            }
            $sendingEvents[] = [
                'name' => $event,
                'params' => array_merge($defaultParams, $params)
            ];
        }
        $this->analyticsEvents($sendingEvents);
    }

    protected function get($key, $default = null)
    {
        return data_get($this->data, $key, $default);
    }

    protected function addedDeal(): void
    {
        $this->addActionToRequest();

        $params = $this->getGA4Params();
        $this->analyticsEvents([['name' => 'lead', 'params' => $params]]);

        $adsBudget = $this->getCustomFieldValue('db97e6a512f2df482f3cbda60b002bfb21e5c7ea');
        if ($adsBudget && in_array((int)$adsBudget, [430, 431, 432], true)) {
            $this->analyticsEvents([['name' => 'lead_mql', 'params' => $params]]);
        }
    }

    protected function updatedDeal(): void
    {
        $status = $this->get('data.status');
        $statusPrev = $this->get('previous.status', $status);

        if ($status !== $statusPrev && in_array($status, ['lost', 'deleted'])) {
            $this->unsubscribeMemberMailchimp($this->getPersonEmail());
        }

        $this->processAnalyticsEvents();
    }

    protected function deletedDeal(): void
    {
        $this->addActionToRequest();
        $this->unsubscribeMemberMailchimp($this->getPersonEmail());
    }

    private const array LEAD_REQUEST_RELATION = [
        422 => 'ppc',
        423 => 'csd',
        425 => 'email',
        447 => 'main'
    ];

    private const array TYPE_OF_APPEAL_RELATIONS = [
        411 => 'project',
        418 => 'partner',
        412 => 'diagnostic',
        414 => 'cases',
        415 => 'standard',
        416 => 'scaling',
        417 => 'price',
    ];

    protected function addEventToMailchimp(string $prefix): void
    {
        $lead_request = $this->getCustomFieldValue('02b414ab0d23c8c795fe81e0a7a6897b735fb0e1');
        $type_of_appeal = $this->getCustomFieldValue('b02651705cf51729bb23384b618a7cc52228689f');
        if (
            empty(self::LEAD_REQUEST_RELATION[$lead_request])
            || empty(self::TYPE_OF_APPEAL_RELATIONS[$type_of_appeal])
            || (!$email = $this->getPersonEmail())
        ) {
            return;
        }

        $event = $prefix.'_'.self::LEAD_REQUEST_RELATION[$lead_request].'_'.self::TYPE_OF_APPEAL_RELATIONS[$type_of_appeal];

        Mailchimp::createListMemberEvent($email, $event);
    }

    protected function unsubscribeMemberMailchimp(?string $email): void
    {
        if (!$email) {
            return;
        }
        Mailchimp::setListMember($email, [
            "status_if_new" => "unsubscribed",
            "status" => "unsubscribed"
        ]);
    }

    protected function getPersonEmail()
    {
        $personId = $this->get('previous.person_id', $this->get('data.person_id'));

        if (!$personId || (!$person = PipeDrive::getPerson($personId))) {
            return null;
        }
        return $person['primary_email'] ?? null;
    }

    protected function analyticsEvents(array $events): void
    {
        $fbCApiService = new FacebookConversionsService();
        if ($fbUserId = $this->getCustomFieldValue('ffbe4b814d568dbc0bd6c81e19b3062c4923561f')) {
            $fbEventData = $fbCApiService->getEventData($fbUserId);
        }

        $facebookLeadId = $this->getCustomFieldValue('8fcf5b7113426460f1c731698e4ef55f9a19756d');

        $url = $this->getCustomFieldValue('25f0bbab67a342dfddbc6584ac3a628a66bd9513') ?: '';

        $idGAds = str_contains($url, 'lp.coerandig.site') ? 9 : 6;
        $gAdsService = new GoogleOfflineConversionsService();
        /** @var GoogleAdsAccount $gAdsAccount */
        $gAdsAccount = GoogleAdsAccount::find($idGAds);

        $idFbCApi = str_contains($url, '.adsquiz.io') ? 173 : 68;

        foreach ($events as $event) {
            if ($fbUserId && !empty($fbEventData)) {
                $customData = $event['name'] === 'purchase' ? [
                    'currency' => $event['params']['currency'],
                    'value' => $event['params']['value']
                ] : ['status' => true];
                if ($url = $this->getCustomFieldValue('25f0bbab67a342dfddbc6584ac3a628a66bd9513')){
                    $param = match (true) {
                        str_contains($url, 'csd') => 'csd',
                        str_contains($url, 'online-ads') => 'online_ads',
                        default => null,
                    };
                    if ($param){
                        $customData[$param] = 1;
                        $customData['source'] = $param;
                    }
                }
                $fbCApiService->dispatchEvent(
                    [
                        'event_source_url' => $event['params']['page_location'] ?? 'https://median-ads.com/',
                        'event_name' => str_replace('_', '', Str::convertCase($event['name'], 2)),
                        'custom_data' => $customData
                    ] + $fbEventData,
                    FacebookPixel::cache($idFbCApi)
                );
            }
            if ($facebookLeadId){
                $data = $fbCApiService->getCrmEvent(
                    $facebookLeadId,
                    str_replace('_', '', Str::convertCase($event['name'], 2)),
                    'PipeDrive'
                );
                $fbCApiService->dispatchEvent($data, FacebookPixel::cache(68));
            }

            if (!empty($event['params']['gclid']) && $gAdsAccount) {
                $gAdsService->handleEvent($gAdsAccount, [
                    'gclid' => $event['params']['gclid'],
                    'event' => $event['name'],
                    'value' => $event['params']['value'] ?? 1,
                    'currency' => $event['params']['currency'] ?? 'USD',
                ]);
            }
        }

        if ($clientId = $this->getCustomFieldValue('b8a91a19e62cc439261626c730d7d101270235d9')) {
            $this->sendEventToGA4($events, $clientId);
        }
    }

    protected function sendEventToGA4(array $events, string $clientId): void
    {
        $prefixConfig = '';
        $url = $this->getCustomFieldValue('25f0bbab67a342dfddbc6584ac3a628a66bd9513') ?: '';

        $prefixConfig = match (true){
            str_contains($url, 'median-ads.agency') => 'agency_',
            str_contains($url, 'lp.coerandig.site') => 'coerandig_',
            default => ''
        };

        $url = 'https://www.google-analytics.com/mp/collect'
            .'?measurement_id='.config('services.google-measurement-protocol.'.$prefixConfig.'measurement_id')
            .'&api_secret='.config('services.google-measurement-protocol.'.$prefixConfig.'api_secret');
        $data = [
            'client_id' => $clientId,
            'timestamp_micros' => time() * 1000000,
            'events' => array_values($events)
        ];
        Http::asJson()->post($url, $data);
    }

    private const array RELATION_PARAMS = [
        'content' => '2bb9509ab03f0ca06256f5323ca16c79f39106df',
        'medium' => '57f40515431ef1041cbed535babed1182c14c791',
        'campaign' => '6f1c1a22830bf51f79cc409dcb7210050db0ab9c',
        'source' => 'd2240f230fc6fd71100b0df38691cd72c3b73810',
        'term' => '1574457b20dcb38da5cfe87f67d63d595c2a17ae',
        'session_id' => 'afcac4620fa8518dc324f4b95f22c1a84e97b42e',
        'gclid' => '00961d439cd1fdc613689d81fb1d06b9aff03c36',
        'page_location' => '25f0bbab67a342dfddbc6584ac3a628a66bd9513'
    ];

    protected function getCustomFieldValue(string $code, bool $prevData = false): mixed
    {
        $object = $prevData ? 'previous' : 'data';
        $field = $this->get($object.'.custom_fields.'.$code);
        if (empty($field) && !$prevData) {
            return null;
        }

        if (!$field = $this->get('data.custom_fields.'.$code)) {
            return null;
        }

        return match ($field['type']) {
            'monetary' => $field['value'].$field['currency'],
            'timerange', 'daterange' => [$field['from'], $field['until']],
            'set' => array_column($field['values'], 'id'),
            default => $field['value'] ?? $field['id']
        };
    }

    protected function getGA4Params(): array
    {
        $params = [
//            'debug_mode' => true
        ];

        foreach (self::RELATION_PARAMS as $param => $code) {
            if ($value = $this->getCustomFieldValue($code)) {
                $params[$param] = is_numeric($value) ? (int)$value : $value;
            }
        }
        return $params;
    }


    public function failed(Throwable $exception): void
    {
        $this->dataJob->update([
            'status' => JobStatus::ERROR,
            'message' => $exception->getMessage()
        ]);
    }
}
