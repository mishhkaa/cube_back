<?php

namespace App\Services\Conversions;

use App\Classes\WebScriptContent;
use App\Contracts\ConversionAccountInterface;
use App\Contracts\IntegrationServiceInterface;
use App\Contracts\IntegrationWithUserTracking;
use App\Facades\RequestLog;
use App\Jobs\EventsSenders\FbEventSenderJob;
use App\Models\DataJob;
use App\Models\FacebookPixel;
use App\Models\TrackingUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FacebookConversionsService implements IntegrationServiceInterface, IntegrationWithUserTracking
{
    public const array ALLOWED_USER_DATA_KEYS = [
        'em',
        'ph',
        'fn',
        'ln',
        'external_id',
        'client_ip_address',
        'client_user_agent',
        'fbc',
        'fbp',
        'lead_id'
    ];

    protected const array HASH_USER_DATA_KEYS = ['em', 'ph', 'fn', 'ln', 'external_id'];

    public function handleClientEvent(FacebookPixel $pixel, array $data): string
    {
        $externalId = data_get($data, 'user_data.external_id') ?: Str::ulid()->toString();

        $userData = $this->getUpdateUserData($externalId, true, array_filter($data['user_data']), $data['fbclid'] ?? null);

        $isCustomEvent = str_starts_with($data['event_name'], 'UTM_') || !in_array($data['event_name'], ['init', 'Purchase', 'AddToCart', 'InitiateCheckout', 'ViewContent', 'AddToWishlist', 'AddPaymentInfo', 'Lead', 'Contact', 'SubmitApplication', 'Subscribe', 'CompleteRegistration']);

        if ($data['event_name'] === 'init' || (!$pixel->testing && empty($userData['fbc']) && !$isCustomEvent)) {
            return $externalId;
        }

        $eventData = [
            'event_name' => $data['event_name'],
            'event_time' => time(),
            'action_source' => 'website',
            'event_source_url' => $data['event_source_url'],
            'user_data' => $userData,
        ];

        if ($custom_data = data_get($data, 'custom_data')) {
            $eventData['custom_data'] = $custom_data;
        }

        if (!empty($data['event_id'])){
            $eventData['event_id'] = $data['event_id'];
        }

        $this->dispatchEvent($eventData, $pixel);
        return $externalId;
    }

    public function getCrmEvent(int|string $leadId, string $eventName, $nameCrm = 'CRM'): array
    {
        return [
            'event_name' => $eventName,
            'event_time' => time(),
            'user_data' => [
                'lead_id' => (int)$leadId
            ],
            'action_source' => 'system_generated',
            'custom_data' => [
                'event_source' => 'crm',
                'lead_event_source' => $nameCrm ?: 'CRM'
            ]
        ];
    }

    private function filterUserData(array $data): array
    {
        return array_intersect_key($data, array_flip(self::ALLOWED_USER_DATA_KEYS));
    }

    private function getUpdateUserData(TrackingUser|string $user, bool $createIfNotUser = false, array $userData = [], ?string $fbclid = null): ?array
    {
        return Cache::lockTrackingUser($user, fn () => $this->processGetUpdateUserData($user, $createIfNotUser, $userData, $fbclid));
    }

    private function processGetUpdateUserData(TrackingUser|string $user, bool $createIfNotUser = false, array $userData = [], ?string $fbclid = null) : ?array
    {
        if (is_string($user)){
            $externalId = $user;
            $user = TrackingUser::find($user);

            if (!$createIfNotUser) {
                return $user && self::userBelongToIntegration($user) ? $this->filterUserData($user->data) : null;
            }

            if (!$user) {
                $user = TrackingUser::newModelInstance(['id' => $externalId, 'data' => []]);
            }
        }

        $data = $user->data;

        $userData['external_id'] = $user->id;
        $userData = $this->filterUserData($userData);

        foreach ($userData as $key => $datum) {
            if (in_array($key, self::HASH_USER_DATA_KEYS, true)) {
                $userData[$key] = md5($datum);
            }
        }

        if ($fbc = match (true){
            !$fbclid && !empty($data['fbc']),
                $fbclid && !empty($data['fbc']) && str_contains($data['fbc'], $fbclid) => $data['fbc'],
            (bool)$fbclid => 'fb.1.'.round(microtime(true) * 1000).'.'.$fbclid,
            default => null,
        }){
            $userData['fbc'] = $fbc;
        }

        if (empty($userData['fbp']) && empty($data['fbp'])) {
            $userData['fbp'] = 'fb.1.'.round(microtime(true) * 1000).'.'.random_int(1111111111, 9999999999);
        }

        $userData += $data;
        $userData = array_filter($userData);

        $user->data = $userData;
        $user->save();

        return $this->filterUserData($userData);
    }

    public function getEventData(TrackingUser|string $user, ?string $eventName = null, ?string $url = null): ?array
    {
        $userData = $this->getUpdateUserData($user);

        if (!$userData) {
            return null;
        }

        return array_filter([
            'user_data' => $userData,
            'event_time' => time(),
            'action_source' => 'website',
            'event_name' => $eventName,
            'event_source_url' => $url
        ]);
    }

    public function getEventDataOrCreate(TrackingUser|string $user, ?string $eventName = null, ?string $url = null): ?array
    {
        $userData = $this->getUpdateUserData($user, true);

        if (!$userData) {
            return null;
        }

        return array_filter([
            'user_data' => $userData,
            'event_time' => time(),
            'action_source' => 'website',
            'event_name' => $eventName,
            'event_source_url' => $url
        ]);
    }
    public function dispatchEvent(array $data, ConversionAccountInterface $account): void
    {
        dispatch(static function () use ($account, $data) {
            $dataJob = DataJob::query()->create([
                'queue' => FacebookPixel::getSourceName(),
                'payload' => $data,
                'event' => $account->id,
                'action' => $data['event_name'],
                'request_id' => RequestLog::getId()
            ]);
            FbEventSenderJob::dispatch($dataJob, $account->id);
        })->afterResponse();
    }

    public function getUserDataByQueryOrExit(string $query = 'user'): ?array
    {
        if (!$user = request()->input($query)) {
            return null;
        }

        return $this->getEventData($user);
    }

    public static function deleteJsCacheFile($id): void
    {
        $fileCache = public_path("partners/js/fb-events-$id.js");
        File::delete($fileCache);
    }

    public function getContentJS($id, bool $forBandle = false): string
    {
        $fbSiteEventFile = resource_path("js/fb-events/$id.js");

        $account = FacebookPixel::cache($id);

        if (!$account) {
            $content = !$forBandle ? 'console.log("Account not found. Please remove this script from your site.");' : '';
        } elseif (!$account->active) {
            $content = '';
        } else {
            $content = !$forBandle ? WebScriptContent::helper() : '';
            $content .= file_get_contents(resource_path('js/fb-events/index.min.js'));
            $testing = $account->testing ? 'false' : 'true';
            $content .= "FbEvents.setConfig($id, '$account->currency', $testing);".PHP_EOL;
            if (File::isFile($fbSiteEventFile)) {
                $content .= !$forBandle ? WebScriptContent::dataLayer() : '';
                $content .= file_get_contents($fbSiteEventFile);
            }
        }

        if (!$forBandle){
            File::put(public_path("partners/js/fb-events-$id.js"), $content);
        }
        return $content;
    }

    public static function userBelongToIntegration(TrackingUser|null $user): bool
    {
        return !empty($user?->data['fbp']);
    }
}
