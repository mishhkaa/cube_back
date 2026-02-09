<?php

namespace App\Services\Conversions;

use App\Classes\WebScriptContent;
use App\Contracts\ConversionAccountInterface;
use App\Contracts\IntegrationServiceInterface;
use App\Contracts\IntegrationWithUserTracking;
use App\Facades\RequestLog;
use App\Jobs\EventsSenders\TikTokEventSenderJob;
use App\Models\DataJob;
use App\Models\TikTokPixel;
use App\Models\TrackingUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TikTokEventsService implements IntegrationServiceInterface, IntegrationWithUserTracking
{
    public const array ALLOWED_USER_DATA_KEYS = [
        'phone',
        'email',
        'external_id',
        'ip',
        'user_agent',
        'locale',
        'ttclid',
        'ttp'
    ];

    protected const array HASH_USER_DATA_KEYS = ['phone', 'email', 'external_id'];

    public function handleWebEvent(TikTokPixel $pixel, array $data): string
    {
        $externalId = data_get($data, 'user.external_id') ?: Str::ulid()->toString();

        $data['user'] = $this->getUpdateUserData($externalId, true, $data['user'] ?? []);

        if ($data['event'] === 'init' || (!$pixel->testing && empty($data['user']['ttclid']))) {
            return $externalId;
        }

        $data += [
            'event_time' => time(),
            'event_id' => Str::uuid()->toString()
        ];

        $this->dispatchEvent($data, $pixel);

        return $externalId;
    }

    public function getEventData(TrackingUser|string $user, ?string $eventName = null, ?string $pageUrl = null): ?array
    {
        if (!$user) {
            return null;
        }
        $user = $this->getUpdateUserData($user);

        if (!$user) {
            return null;
        }

        return array_filter([
            'event' => $eventName,
            'user' => $user,
            'event_time' => time(),
            'event_id' => Str::uuid()->toString(),
            'page' => $pageUrl ? ['url' => $pageUrl] : null
        ]);
    }

    public function getCrmEvent(string $ledId, string $event, ?array $user = null, ?array $properties = null, ?string $nameCrm = null): array
    {
        if ($user) {
            $user = $this->hashingUserData($this->filterUserData($user, ['external_id', 'email', 'phone']));
        }
        return array_filter([
            'event' => $event,
            'properties' => $properties,
            'user' => $user,
            'lead' => array_filter([
                'lead_id' => $ledId,
                'lead_event_source' => $nameCrm
            ]),
            'event_time' => time(),
            'event_id' => Str::uuid()->toString()
        ]);
    }

    private function getUpdateUserData(TrackingUser|string $user, bool $createIfNotUser = false, array $userData = []): ?array
    {
        return Cache::lockTrackingUser($user, fn () => $this->processGetUpdateUserData($user, $createIfNotUser, $userData));
    }

    private function processGetUpdateUserData(TrackingUser|string $user, bool $createIfNotUser = false, array $userData = []): ?array
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

        $userData['external_id'] = $user->id;

        $userData = $this->filterUserData($userData);
        $userData = $this->hashingUserData($userData);

        $userData += $user->data;

        $user->data = $userData;
        $user->save();

        return $this->filterUserData($userData);
    }

    private function filterUserData(array $data, array $allowedKeys = self::ALLOWED_USER_DATA_KEYS): array
    {
        return array_intersect_key($data, array_flip($allowedKeys));
    }

    private function hashingUserData(array $userData): array
    {
        foreach ($userData as $key => $datum) {
            if (in_array($key, self::HASH_USER_DATA_KEYS, true)) {
                $userData[$key] = md5($datum);
            }
        }
        return $userData;
    }

    public function dispatchEvent(array $data, ConversionAccountInterface $account, string $eventSource = 'web'): void
    {
        dispatch(static function () use ($data, $account, $eventSource) {
            $dataJob = DataJob::query()->create([
                'queue' => TikTokPixel::getSourceName(),
                'payload' => $data,
                'event' => $account->id,
                'action' => $data['event'] ?? '',
                'request_id' => RequestLog::getId()
            ]);

            TikTokEventSenderJob::dispatch($dataJob, $account->id, $eventSource);
        })->afterResponse();
    }

    public static function userBelongToIntegration(TrackingUser|null $user): bool
    {
        return !empty($user?->data['ip']);
    }

    public function getContentJS($id, bool $forBandle = false): string
    {
        $accountFile = resource_path("js/tiktok-events/$id.js");

        $pixel = TikTokPixel::cache($id);

        if (!$pixel) {
            $content = !$forBandle ? 'console.log("Account not found. Please remove this script from your site.")' : '';
        } elseif (!$pixel->active) {
            $content = '';
        } else {
            $content = !$forBandle ? WebScriptContent::helper() : '';
            $content .= file_get_contents(resource_path('js/tiktok-events/index.min.js'));
            $testing = $pixel->testing ? 'false' : 'true';
            $content .= "TikTokEvents.setConfig($id, '$pixel->currency', $testing);".PHP_EOL;
            if (File::isFile($accountFile)) {
                $content .= !$forBandle ? WebScriptContent::dataLayer() : '';
                $content .= file_get_contents($accountFile);
            }
        }

        if (!$forBandle) {
            File::put(public_path("partners/js/tiktok-events-$id.js"), $content);
        }

        return $content;
    }

    public static function deleteJsCacheFile($id): void
    {
        $fileCache = public_path("partners/js/tiktok-events-$id.js");
        File::delete($fileCache);
    }
}
