<?php

namespace App\Services\Conversions;

use App\Classes\Enums\XEventName;
use App\Classes\WebScriptContent;
use App\Contracts\ConversionAccountInterface;
use App\Contracts\IntegrationServiceInterface;
use App\Contracts\IntegrationWithUserTracking;
use App\Facades\RequestLog;
use App\Jobs\EventsSenders\XConversionSendJob;
use App\Models\DataJob;
use App\Models\TrackingUser;
use App\Models\XPixel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class XConversionsService implements IntegrationServiceInterface, IntegrationWithUserTracking
{
    public const array ALLOWED_USER_DATA_KEYS = [
        ['twclid'],
        ['hashed_phone_number'],
        ['hashed_email'],
        ['ip_address', 'user_agent'],
    ];

    public const array HASH_USER_DATA_KEYS = ['phone' => 'hashed_phone_number', 'email' => 'hashed_email'];

    public function dispatchEvent(array $data, ConversionAccountInterface $account): void
    {
        dispatch(static function () use ($account, $data) {
            $mapEvents = array_column($account->access_token, 'type', 'id');

            if (empty($data['event']) && empty($data['event_id'])) {
                throw new \RuntimeException('event_id or event is required in data array');
            }

            $event = match (true) {
                empty($data['event']) => $mapEvents[$data['event_id']],
                $data['event'] instanceof XEventName => $data['event']->value,
                default => $data['event']
            };
            unset($data['event']);

            $data['event_id'] = $data['event_id'] ?? array_search($event, $mapEvents, true);

            if (empty($data['event_id'])) {
                throw new \RuntimeException('event_id not found in map events');
            }

            $dataJob = DataJob::query()->create([
                'queue' => XPixel::getSourceName(),
                'payload' => $data,
                'event' => $account->id,
                'action' => $event,
                'request_id' => RequestLog::getId()
            ]);
            XConversionSendJob::dispatch($dataJob, $account->id);
        })->afterResponse();
    }

    public static function userBelongToIntegration(?TrackingUser $user): bool
    {
        return !empty($user?->data['ip_address']);
    }

    public function handleEvent(XPixel $pixel, array $data): string
    {
        $externalId = data_get($data, 'identifiers.external_id') ?: Str::ulid()->toString();

        $identifiers = $this->getUpdateUserData($externalId, true, $data['identifiers'] ?? []);

        if ($data['event_id'] === 'init' || (!$pixel->testing && empty($identifiers[0]['twclid']))) {
            return $externalId;
        }

        $data = array_filter([
            'conversion_time' => date('Y-m-d\TH:i:s.000O'),
            'event_id' => $data['event_id'],
            'identifiers' => $identifiers,
            'currency' => $data['currency'] ?? null,
            'value' => !empty($data['value']) ? (float)$data['value'] : null,
            'conversion_id' => $data['conversion_id'] ?? null,
            'contents' => $data['contents'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        $this->dispatchEvent($data, $pixel);

        return $externalId;
    }

    public function getEventData(TrackingUser|string $user, XEventName|string|null $eventName = null): ?array
    {
        $userData = $this->getUpdateUserData($user);

        if (!$userData) {
            return null;
        }

        return array_filter([
            'conversion_time' => date('Y-m-d\TH:i:s.000O'),
            'event' => $eventName,
            'identifiers' => $userData,
        ]);
    }

    private function getUpdateUserData(TrackingUser|string $user, bool $createIfNotUser = false, array $userData = []): ?array
    {
        return Cache::lockTrackingUser($user, fn () => $this->processingGetUpdateUserData($user, $createIfNotUser, $userData));
    }

    private function processingGetUpdateUserData(TrackingUser|string $user, bool $createIfNotUser = false, array $userData = []): ?array
    {
        if (is_string($user)) {
            $externalId = $user;

            $user = TrackingUser::find($externalId);

            if (!$createIfNotUser) {
                return $user && self::userBelongToIntegration($user) ? $user->data : null;
            }

            if (!$user) {
                $user = TrackingUser::newModelInstance(['id' => $externalId, 'data' => []]);
            }
        }

        $userData += $user->data;

        $user->data = $userData;
        $user->save();

        $userData = $this->hashingUserData($userData);

        $sendData = [];
        foreach (self::ALLOWED_USER_DATA_KEYS as $i => $keys) {
            foreach ($keys as $key) {
                if (!empty($userData[$key])) {
                    $sendData[$i][$key] = $userData[$key];
                }
            }
        }

        return array_values($sendData);
    }

    private function hashingUserData(array $userData): array
    {
        foreach ($userData as $key => $datum) {
            if (!empty(self::HASH_USER_DATA_KEYS[$key])) {
                $userData[self::HASH_USER_DATA_KEYS[$key]] = md5($datum);
            }
        }
        return $userData;
    }

    public function getContentJS($id, bool $forBandle = false): string
    {
        $accountFile = resource_path("js/x-events/$id.js");

        $pixel = XPixel::cache($id);

        if (!$pixel) {
            $content = !$forBandle ? 'console.log("Account not found. Please remove this script from your site.")' : '';
        } elseif (!$pixel->active) {
            $content = '';
        } else {
            $content = !$forBandle ? WebScriptContent::helper() : '';
            $content .= file_get_contents(resource_path('js/x-events/index.min.js'));
            $testing = $pixel->testing ? 'false' : 'true';
            $mapEvents = json_encode(array_column($pixel->access_token, 'id', 'type'));
            $content .= "XEvents.setConfig($id, '$pixel->currency', $testing, $mapEvents);".PHP_EOL;
            if (File::isFile($accountFile)) {
                $content .= !$forBandle ? WebScriptContent::dataLayer() : '';
                $content .= file_get_contents($accountFile);
            }
        }

        if (!$forBandle) {
            File::put(public_path("partners/js/x-events-$id.js"), $content);
        }

        return $content;
    }

    public static function deleteJsCacheFile($id): void
    {
        $fileCache = public_path("partners/js/x-events-$id.js");
        File::delete($fileCache);
    }
}
