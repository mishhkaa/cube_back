<?php

namespace App\Actions;

use App\Models\FacebookPixel;
use App\Models\GoogleAdsAccount;
use App\Models\ScriptBundle;
use App\Models\TrackingUser;
use App\Services\Conversions\FacebookConversionsService;
use App\Services\Conversions\GoogleOfflineConversionsService;
use App\Services\IntegrationsService;
use Illuminate\Http\Request;

class FbCApiSeverEventsAction
{
    public function __construct(protected Request $request, protected FacebookConversionsService $fbCapi)
    {
    }

    public function event139(FacebookPixel $account): ?array
    {
        if (!$user = $this->request->query('click_id')) {
            return null;
        }

        if (!$event = match ($this->request->query('event')) {
            'connect_wallet' => 'ConnectWallet',
            'ftd' => 'Purchase',
            default => null
        }) {
            return null;
        }

        if (!$eventData = $this->fbCapi->getEventData($user, $event, 'https://token.antix.in/')) {
            (new GoogleOfflineConversionsService())->handleEvent(GoogleAdsAccount::cache(1), $this->request->query());
            return null;
        }
        if ($amount = $this->request->query('value')) {
            $eventData['custom_data'] = [
                'value' => (float)$amount,
                'currency' => 'USD'
            ];
        }

        return $eventData;
    }

    public function event97(): ?array
    {
        if (!($user = $this->request->query('user')) || !($trackingUser = TrackingUser::find($user))) {
            return null;
        }

        IntegrationsService::handleCallbackWebEvent(ScriptBundle::find(4), $trackingUser);

        return null;
    }

    // median-ads academy
    public function event68academy(FacebookPixel $account, $eventData): ?array
    {
        if (!$event = $this->request->post('event')) {
            return null;
        }
        if ($event === 'purchase') {
            $event = ucfirst($event);
            $eventData += [
                'custom_data' => [
                    'value' => (float)$this->request->post('value', 0),
                    'currency' => $this->request->post('currency', 'USD'),
                ]
            ];
        }

        $eventData += [
            'event_name' => $event,
            'event_source_url' => 'https://median-grp.com/academy/',
        ];

        return $eventData;
    }

    // ggbet
    public function event88(FacebookPixel $account, array $eventData): ?array
    {
        $event = match ($this->request->query('event')) {
            'reg' => 'CompleteRegistration',
            'ftd' => 'Purchase',
            default => null
        };

        if (!$event) {
            return null;
        }

        $eventData += [
            'event_source_url' => 'https://ggbet.ua/uk-ua',
            'event_name' => $event,
            'custom_data' => $event === 'CompleteRegistration'
                ? ['status' => true]
                : [
                    'value' => $this->request->query('amount', 0),
                    'currency' => $this->request->query('currency', $account->currency)
                ]
        ];

        return $eventData;
    }

    public function event112(FacebookPixel $account, array $eventData): ?array
    {
        $event = $this->request->query('event');

        $eventName = match ($event) {
            'purchase' => 'Purchase',
            default => null
        };
        if (!$eventName) {
            return null;
        }

        $eventData += [
            'event_source_url' => 'https://mir-mexa.com.ua/',
            'event_name' => $eventName,
            'custom_data' => $this->request->post()
        ];

        return $eventData;
    }

    public function event127(FacebookPixel $account): ?array
    {
        $queryUser = $this->request->query('user');

        if (str_contains($queryUser, 'event')) {
            $url = parse_url($queryUser);
            $queryUser = $url['path'] ?? '';
            parse_str($url['query'] ?? '', $query);
            $event = $query['event'] ?? null;
        } else {
            $event = $this->request->query('event');
        }

        if (!$event) {
            return null;
        }

        if (!$eventData = $this->fbCapi->getEventData($queryUser, $event, 'https://4b.ua/')) {
            return null;
        }

        $eventData += [
            'custom_data' => [
                'value' => $this->request->query('value', 0),
                'currency' => $this->request->query('currency', $account->currency)
            ]
        ];

        return $eventData;
    }

    public function event129(FacebookPixel $account): ?array
    {
        if (!$user = $this->request->query('click_id')) {
            return null;
        }

        $event = match ($this->request->query('event')) {
            'reg' => 'CompleteRegistration',
            'ftd' => 'Purchase',
            default => null
        };

        if (!$event) {
            return null;
        }

        if (!$eventData = $this->fbCapi->getEventData($user, $event, 'https://landing.casino.ua/')){
            return null;
        }

        $eventData += [
            'custom_data' => $event === 'CompleteRegistration'
                ? ['status' => true]
                : [
                    'value' => $this->request->query('amount', 1),
                    'currency' => $this->request->query('currency', $account->currency)
                ]
        ];

        return $eventData;
    }

    public function event130(FacebookPixel $account, array $eventData): ?array
    {
        if (!$eventData) {
            return null;
        }
        if (!$event = $this->request->query('event')) {
            return null;
        }

        // Маппінг Install -> Lead для кліків на кнопку завантаження
        // Install для скачування апки залишається Install (надсилається з мобільного SDK з source=app)
        $source = $this->request->query('source');
        $eventName = match (true) {
            $event === 'Install' && $source !== 'app' => 'Lead', // Клік на кнопку = Lead
            default => $event
        };

        $eventData += [
            'event_source_url' => 'https://trustplus.site/',
            'event_name' => $eventName,
        ];

        if ($event === 'Subscribe') {
            $eventData['custom_data'] = [
                'value' => 0,
                'currency' => 'USD',
                'predicted_ltv' => 1
            ];
        }

        return $eventData;
    }

    public function event107(FacebookPixel $account): ?array
    {
        if (!($user = $this->request->query('click_id')) || !($trackingUser = TrackingUser::find($user))) {
            return null;
        }

        IntegrationsService::handleCallbackWebEvent(ScriptBundle::find(12), $trackingUser);

        return null;
    }

    public function event137(FacebookPixel $account, array $eventData): ?array
    {
        if (!$eventData) {
            return null;
        }

        $event = match ($this->request->query('stage')) {
            'new' => 'LeadNew',
            'Консультація по телефону' => 'LeadValid',
            "Запис на консультацію до лікаря" => 'LeadQualified',
            default => null
        };

        if (!$event) {
            return null;
        }

        $eventData += [
            'event_source_url' => 'https://dentalartodesa.adsquiz.io/',
            'event_name' => $event,
        ];

        return $eventData;
    }

    public function event144(FacebookPixel $account, array $eventData): ?array
    {
        if (!$eventData) {
            return null;
        }

        if ($this->request->query('event') !== 'purchase' && $this->request->input('event') !== 'purchase') {
            return null;
        }

        $eventSourceUrl = $this->request->query('event_source_url') ?? $this->request->input('event_source_url');

        $eventData += [
            'event_name' => 'Purchase',
            'event_source_url' => $eventSourceUrl,
            'custom_data' => [
                'value' => (float)($this->request->query('value') ?? $this->request->input('value', 1)),
                'currency' => $this->request->query('currency') ?? $this->request->input('currency', $account->currency),
            ],
        ];

        return $eventData;
    }

    public function event145(FacebookPixel $account): ?array
    {
        if (!$eventData = $this->fbCapi->getUserDataByQueryOrExit('click_id')) {
            return null;
        }

        $event = match ($this->request->input('event')) {
            'lead' => 'Lead',
            'lead_valid' => 'LeadValid',
            'purchase' => 'Purchase',
            default => null
        };

        if (!$event) {
            return null;
        }

        $eventData += [
            'event_source_url' => 'https://study-ffa-edu.com/',
            'event_name' => $event,
        ];

        if ($event === 'Purchase') {
            $eventData['custom_data'] = [
                'value' => $this->request->query('value', 1),
                'currency' => $this->request->query('currency', $account->currency)
            ];
        }

        return $eventData;
    }

    public function event147(FacebookPixel $account, array $eventData): ?array
    {
        if (!$event = $this->request->query('event')) {
            return null;
        }

        $eventData += [
            'event_source_url' => 'https://tulong.store/',
            'event_name' => $event,
        ];

        return $eventData;
    }

    public function event151(FacebookPixel $account, array $eventData): ?array
    {
        $event = match ($this->request->query('event')) {
            'reg' => 'CompleteRegistration',
            'ftd' => 'Purchase',
            default => null
        };

        if (!$event) {
            return null;
        }

        $eventData += [
            'event_name' => $event,
            'event_source_url' => 'https://promoxonpl.online/',
            'custom_data' => $event === 'CompleteRegistration'
                ? ['status' => true]
                : [
                    'value' => 1,
                    'currency' => $account->currency
                ]
        ];

        return $eventData;
    }

    public function event184(FacebookPixel $account, array $eventData, int|string|null $externalId = null): ?array
    {
        $event = match ($this->request->query('event')) {
            'reg' => 'CompleteRegistration',
            'ftd' => 'Purchase',
            default => null
        };

        if (!$event) {
            return null;
        }

        $eventSourceUrl = $this->request->query('event_source_url', 'https://orobet.com/en/');

        $eventData += [
            'event_name' => $event,
            'event_source_url' => $eventSourceUrl,
            'custom_data' => $event === 'CompleteRegistration'
                ? ['status' => true]
                : [
                    'value' => $this->request->query('amount', 1),
                    'currency' => $this->request->query('currency', $account->currency)
                ]
        ];

        return $eventData;
    }

    public function event186(FacebookPixel $account, array $eventData = []): ?array
    {
        $event = match ($this->request->query('event')) {
            'reg' => 'CompleteRegistration',
            'lead' => 'Lead',
            'purchase' => 'Purchase',
            'contact' => 'Contact',
            default => null
        };

        if (!$event) {
            return null;
        }

        $postData = $this->request->post();

        if (isset($postData['data']) && is_array($postData['data'])) {
            $dataFromSalesDrive = $postData['data'];
            // Об'єднуємо дані з data в основний масив
            $postData = array_merge($postData, $dataFromSalesDrive);
            unset($postData['data']);
        }

        $externalId = $postData['external_id']
            ?? $postData['medianuserid']
            ?? $postData['externalId']
            ?? null;

        if ($externalId) {
            if (!$eventData = $this->fbCapi->getEventData($externalId, $event, $this->request->query('event_source_url'))) {
                return null;
            }
        }

        unset($postData['external_id']);
        unset($postData['medianuserid']);
        unset($postData['externalId']);

        if (isset($postData['contents']) && !isset($postData['items'])) {
            $postData['items'] = $postData['contents'];
            unset($postData['contents']);
        }

        if ($event === 'Purchase') {
            $customData = [
                'value' => (float)($this->request->query('value')
                    ?? $this->request->post('value')
                    ?? $postData['value']
                    ?? $postData['amount']
                    ?? $postData['paymentAmount']
                    ?? $postData['payment_amount']
                    ?? 0),
                'currency' => $this->request->query('currency')
                    ?? $this->request->post('currency')
                    ?? $postData['currency']
                    ?? $account->currency
            ];

            if (isset($postData['items'])) {
                $customData['items'] = $postData['items'];
            }

            $eventData += [
                'event_name' => $event,
                'custom_data' => $customData
            ];
        } else {
            if ($event === 'Lead') {
                $leadValue = $this->request->query('value')
                    ?? $this->request->post('value')
                    ?? $postData['value']
                    ?? $postData['amount']
                    ?? $postData['paymentAmount']
                    ?? $postData['payment_amount']
                    ?? null;

                if ($leadValue !== null && !isset($postData['value'])) {
                    $postData['value'] = (float)$leadValue;
                }
            }

            $eventData += [
                'event_name' => $event,
                'custom_data' => $postData
            ];
        }

        if ($eventSourceUrl = $this->request->query('event_source_url')) {
            $eventData['event_source_url'] = $eventSourceUrl;
        }

        return $eventData;
    }

    public function event189(FacebookPixel $account, array $eventData): ?array
    {
        $event = match ($this->request->query('event')) {
            'lead' => 'Lead',
            'purchase' => 'Purchase',
            'contact' => 'Contact',
            default => null
        };

        if (!$event) {
            return null;
        }

        $postData = $this->request->post();

        unset($postData['external_id']);

        if (isset($postData['contents']) && !isset($postData['items'])) {
            $postData['items'] = $postData['contents'];
            unset($postData['contents']);
        }

        $eventData += [
            'event_name' => $event,
            'custom_data' => $postData
        ];

        if ($eventSourceUrl = $this->request->query('event_source_url')) {
            $eventData['event_source_url'] = $eventSourceUrl;
        }

        return $eventData;
    }

    public function event190(FacebookPixel $account, array $eventData): ?array
    {
        $event = match ($this->request->query('event')) {
            'reg' => 'CompleteRegistration',
            'CompleteRegistration' => 'CompleteRegistration',
            'CompleteRegistrartion' => 'CompleteRegistration', // Обробка опечатки
            'verification' => 'Verification',
            'purchase' => 'Purchase',
            default => null
        };

        if (!$event) {
            return null;
        }

        $eventData += [
            'event_name' => $event,
            'event_source_url' => $this->request->query('event_source_url', 'https://qpdates.com/'),
        ];

        if ($event === 'Purchase') {
            $eventData['custom_data'] = [
                'value' => (float)$this->request->query('value', 1),
                'currency' => $this->request->query('currency', $account->currency)
            ];
        } else if ($event === 'Verification') {
            $eventData['custom_data'] = [
                'status' => true
            ];
        } else if ($event === 'CompleteRegistration') {
            $eventData['custom_data'] = [
                'status' => true
            ];
        }

        return $eventData;
    }

    public function event192(FacebookPixel $account, array $eventData = [], int|string|null $externalId = null): ?array
    {
        $user = $externalId ?? $this->request->query('click_id');

        if (!$user || !($trackingUser = TrackingUser::find($user))) {
            return null;
        }

        IntegrationsService::handleCallbackWebEvent(ScriptBundle::find(46), $trackingUser);

        return null;
    }
}
