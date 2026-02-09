<?php

namespace App\Actions\IntegrationsCallback;

use App\Models\GoogleAdsAccount;
use App\Models\GoogleSheetAccount;
use App\Models\TrackingUser;
use App\Services\Conversions\GoogleOfflineConversionsService;
use App\Services\GoogleSheetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleAdsAction extends IntegrationAction
{
    public function __construct(private readonly GoogleOfflineConversionsService $service, private readonly Request $request)
    {
    }

    public function event4(GoogleAdsAccount $account, TrackingUser $user): null
    {
        $event = $this->request->query('event');
        if (!in_array($event, ['lead', 'send', 'purchase'], true)) {
            return null;
        }

        $this->service->handleEvent($account, [
            'event' => $event,
            'click_id' => $user->id,
            'value' => $this->request->query('value') || 1,
            'currency' => 'UAH'
        ]);

        return null;
    }

    public function event12(GoogleAdsAccount $account, TrackingUser $user)
    {
        $event = $this->request->query('event');
        if (!in_array($event, ['reg', 'ftd'], true)) {
            return null;
        }

        $this->service->handleEvent($account, [
            'event' => $event,
            'click_id' => $user->id,
            'value' => $this->request->query('amount') || 1,
            'currency' => $this->request->query('currency', $account->currency)
        ]);

        return null;
    }

    public function event16(GoogleAdsAccount $account, TrackingUser $user)
    {
        $event = $this->request->query('event');
        if (!in_array($event, ['reg', 'ftd'], true)) {
            return null;
        }

        $this->service->handleEvent($account, [
            'event' => $event,
            'click_id' => $user->id,
            'value' => (float)($this->request->query('value') || 1),
            'currency' => 'USD'
        ]);

        return null;
    }

    public function event18(GoogleAdsAccount $account, TrackingUser $user)
    {
        if (!$event = $this->request->query('event')) {
            return null;
        }

        $this->service->handleEvent($account, [
            'event' => $event,
            'click_id' => $user->id,
            'value' => (float)($this->request->query('value') || 1),
            'currency' => $this->request->query('currency') ?: 'UAH'
        ]);

        return null;
    }

    /**
     * Callback /partners/callback/45 (бандл 45).
     * Offline conversion (gads 24): click_id, gclid, event, value, currency, time — без змін.
     * Sheet 32 (CRM): нова структура — event, order_number, status, created_at, ordered_at, event_date, event_time, phone, email, order_sum, people_count, order_type, city, office, utm_*, gclid, external_id, is_direct.
     */
    public function event45(GoogleAdsAccount $account, TrackingUser $user): null
    {
        $post = $this->request->post();
        $customFields = $post['custom_fields'] ?? [];
        $gclid = $post['gclid']
            ?? (is_array($customFields) ? ($customFields['gclid_305'] ?? null) : null)
            ?? ($user->data['gclid'] ?? null);
        if ($gclid === '') {
            $gclid = null;
        }

        $event = $post['event'] ?? 'new_lead';
        $value = isset($post['order_sum']) ? (float) $post['order_sum'] : (isset($post['total']) ? (float) $post['total'] : (isset($post['value']) ? (float) $post['value'] : 0));
        $time = $post['created_at'] ?? $post['ordered_at'] ?? $post['time'] ?? null;

        $payloadForGads = array_filter([
            'click_id' => $user->id,
            'gclid' => $gclid,
            'event' => $event,
            'value' => $value,
            'currency' => $post['currency'] ?? 'UAH',
            'time' => $time,
        ], fn ($v) => $v !== null && $v !== '');

        $clickIdReturned = $this->service->handleEvent($account, $payloadForGads);
        if ($clickIdReturned === null && $payloadForGads !== []) {
            Log::info('Bundle 45: offline conversion not sent (no gclid or duplicate event)', [
                'click_id' => $user->id,
                'event' => $event,
                'has_gclid_in_payload' => !empty($payloadForGads['gclid']),
                'has_gclid_in_user' => !empty($user->data['gclid']),
            ]);
        }

        $sheetAccount = GoogleSheetAccount::find(32);
        if ($sheetAccount) {
            $rowForSheet = $this->buildSheet32RowForBundle45($post, $user);
            app(GoogleSheetService::class)->handle($sheetAccount, [$rowForSheet]);
        }

        return null;
    }

    /**
     * Рядок для sheet 32 (бандл 45). Використовується в event45 і в callback без користувача.
     */
    public function buildSheet32RowForBundle45(array $post, ?TrackingUser $user): array
    {
        $event = $post['event'] ?? 'new_lead';
        $gclidFromUser = $user?->data['gclid'] ?? '';

        return [
            'event' => $event,
            'order_number' => $post['order_number'] ?? '',
            'status' => $post['status'] ?? '',
            'created_at' => $post['created_at'] ?? '',
            'ordered_at' => $post['ordered_at'] ?? '',
            'event_date' => $post['event_date'] ?? '',
            'event_time' => $post['event_time'] ?? '',
            'phone' => $post['phone'] ?? '',
            'email' => $post['email'] ?? '',
            'order_sum' => $post['order_sum'] ?? $post['total'] ?? '',
            'people_count' => $post['people_count'] ?? '',
            'order_type' => $post['order_type'] ?? '',
            'city' => $post['city'] ?? '',
            'office' => $post['office'] ?? '',
            'utm_source' => $post['utm_source'] ?? '',
            'utm_medium' => $post['utm_medium'] ?? '',
            'utm_campaign' => $post['utm_campaign'] ?? '',
            'utm_content' => $post['utm_content'] ?? '',
            'utm_term' => $post['utm_term'] ?? '',
            'gclid' => $post['gclid'] ?? $gclidFromUser,
            'external_id' => $post['external_id'] ?? $post['user_id'] ?? $post['click_id'] ?? ($user?->id ?? ''),
            'is_direct' => $post['is_direct'] ?? '',
            'webhook_executed_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
