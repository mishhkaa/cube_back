<?php

namespace App\Services\Conversions;

use App\Classes\Csv;
use App\Classes\WebScriptContent;
use App\Contracts\ConversionAccountInterface;
use App\Contracts\IntegrationServiceInterface;
use App\Contracts\IntegrationWithUserTracking;
use App\Facades\RequestLog;
use App\Models\DataJob;
use App\Models\Enums\JobStatus;
use App\Models\GoogleAdsAccount;
use App\Models\TrackingUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GoogleOfflineConversionsService implements IntegrationServiceInterface, IntegrationWithUserTracking
{
    private function saveGClId(?string $gClId = null, ?string $clickId = null, ?string $checkEvent = null): ?string
    {
        if (!$gClId && !$clickId) {
            return null;
        }

        $clickId = $clickId ?: Str::ulid()->toString();

        return Cache::lockTrackingUser($clickId, fn () => $this->processingSaveGClId($gClId, $clickId, $checkEvent));
    }

    private function processingSaveGClId(?string $gClId = null, ?string $clickId = null, ?string $checkEvent = null): ?string
    {
        $user = TrackingUser::find($clickId);
        if (!$gClId && !self::userBelongToIntegration($user)) {
            return null;
        }

        if (!$user){
            $data = ['gclid' => $gClId, 'events' => []];
            $user = TrackingUser::newModelInstance(['id' => $clickId, 'data' => $data]);
        }elseif($gClId){
            $user->data = array_merge($user->data ?? [], ['gclid' => $gClId, 'events' => []]);
        }

        if (!$this->isNewEvent($user, $checkEvent)) {
            return null;
        }

        $user->save();

        return $clickId;
    }

    public function handleEvent(GoogleAdsAccount $account, array $data): ?string
    {
        if ($data['event'] === 'init') {
            return $this->saveGClId($data['gclid'] ?? null, $data['click_id'] ?? null);
        }

        if (empty($data['gclid']) && empty($data['click_id'])) {
            return null;
        }

        if (empty($data['gclid'])) {
            $gclid = $this->getGClIDbyExternalId($data['click_id'], $data['event']);
        } else {
            if (!empty($data['click_id'])){
                $data['click_id'] = $this->saveGClId($data['gclid'], $data['click_id'], $data['event']);
            }else{
                $data['click_id'] = null;
            }
            $gclid = $data['gclid'];
        }

        if (!$gclid) {
            return null;
        }

        if (!empty($data['time'])){
            $time = is_numeric($data['time'])
                ? Carbon::createFromTimestamp($data['time'])
                : Carbon::parse($data['time']);
        }else{
            $time = now();
        }
        $this->dispatchEvent([
            'gclid' => $gclid,
            'event' => $data['event'],
            'value' => $data['value'] ?? 1,
            'currency' => $data['currency'] ?? $account->currency,
            'time' => $time->format('Y-m-d H:i:s e'),
        ], $account);

        return $data['click_id'];
    }

    public function getGClIDbyExternalId(string $clickId, ?string $event = null): ?string
    {
        $user = TrackingUser::find($clickId);
        if (
            !$user
            || !self::userBelongToIntegration($user)
            || !$this->isNewEvent($user, $event)
        ) {
            return null;
        }

        $user->save();

        return $user->data['gclid'];
    }

    protected function isNewEvent(TrackingUser $user, ?string $event): bool
    {
        if (!$event) {
            return true;
        }

        if (in_array($event, $user->data['events'] ?? [], true)) {
            return false;
        }

        $events = $user->data['events'] ?? [];
        $events[] = $event;
        $user->data = array_merge($user->data, ['events' => $events]);

        return true;
    }

    public function dispatchEvent(array $data, ConversionAccountInterface $account): void
    {
        dispatch(static function () use ($account, $data) {
            DataJob::query()->create([
                'queue' => GoogleAdsAccount::getSourceName(),
                'payload' => $data,
                'event' => $account->id,
                'action' => $data['event'] ?? '',
                'request_id' => RequestLog::getId()
            ]);
        })->afterResponse();
    }

    public static function userBelongToIntegration(TrackingUser|null $user): bool
    {
        return !empty($user?->data['gclid']);
    }

    public function getCSVConversions(GoogleAdsAccount $account, bool $isGoogle): string
    {
        $doneEvents = [];

        /** @var Collection<DataJob> $events */
        $events = $account->events()
            ->when($isGoogle, function ($query) {
                $yesterday = Carbon::now()->subDay();
                $query->whereBetween('created_at', [$yesterday->format('Y-m-d 00:00:00'), $yesterday->format('Y-m-d 23:59:59')]);
            })
            ->get(['id', 'payload']);

        $rows = [["Google Click Id", "Conversion Name", "Conversion Time", "Conversion Value", "Conversion Currency"]];
        foreach ($events as $event) {
            $data = $event->payload;

            $rows[] = [$data['gclid'], $data['event'], $data['time'], $data['value'], $data['currency']];
            $doneEvents[] = $event->id;
        }
        if ($isGoogle){
            DataJob::query()->whereIn('id', $doneEvents)->update(['status' => JobStatus::DONE]);
        }

        return (new Csv())->buildRows($rows);
    }

    public static function clearJsCacheFile($id): void
    {
        $fileCache = public_path("partners/js/gads-conversions-$id.js");
        File::delete($fileCache);
    }

    public function getContentJS($id, bool $forBandle = false): string
    {
        $accountFile = resource_path("js/gads-conversions/$id.js");

        $account = GoogleAdsAccount::cache($id);

        if (!$account){
            $content = !$forBandle ? 'console.log("Account not found. Please remove this script from your site.")' : '';
        } elseif (!$account->active) {
            $content = '';
        } else {
            $content = !$forBandle ? WebScriptContent::helper() : '';
            $content .= file_get_contents(resource_path('js/gads-conversions/index.min.js'));
            $content = str_replace('[[accountId]]', $id, $content);
            if (File::isFile($accountFile)) {
                $content .= !$forBandle ? WebScriptContent::dataLayer() : '';
                $content .= file_get_contents($accountFile);
            }
        }

        if (!$forBandle) {
            File::put(public_path("partners/js/gads-conversions-$id.js"), $content);
        }

        return $content;
    }
}
