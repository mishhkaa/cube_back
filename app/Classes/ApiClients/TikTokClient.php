<?php

namespace App\Classes\ApiClients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TikTokClient
{
    private const string API_URL = 'https://business-api.tiktok.com/open_api/v1.3/';

    private PendingRequest $client;

    public function __construct()
    {
        $this->client = Http::asJson()->baseUrl(self::API_URL)->timeout(300);
    }

    public function setAccessToken(string $accessToken) : static
    {
        $this->client->withHeader('Access-Token', $accessToken);
        return $this;
    }

    public function getAccessToken(string $authCode) :?string
    {
        [$appId, $secret] = $this->getAppIdAndSecret();
        return Http::asJson()
            ->baseUrl(self::API_URL)
            ->post('oauth2/access_token/', [
                'app_id' => $appId,
                'secret' => $secret,
                'auth_code' => $authCode,
            ])
            ->json('data.access_token');
    }

    private function getAppIdAndSecret(): array
    {
        $appId = config('services.tiktok.app_id');
        $secret = config('services.tiktok.secret');
        if (!$appId || !$secret) {
            throw new RuntimeException("TikTok APP_ID and SECRET not set in env file");
        }
        return [$appId, $secret];
    }

    public function getAdAccounts() :?array
    {
        [$appId, $secret] = $this->getAppIdAndSecret();
        return $this->send('oauth2/advertiser/get/', [
            'app_id' => $appId,
            'secret' => $secret
        ])['list'] ?? null;
    }

    private const array ADVERTISER_INFO_FIELDS = ['currency', 'timezone', 'advertiser_id', 'role', 'company', 'status',
        'description', 'rejection_reason', 'name', 'language', 'balance', ];

    public function getAdvertiserInfo(array|string $advertiserIds, array $fields = self::ADVERTISER_INFO_FIELDS): ?array
    {
        return $this->send('advertiser/info', [
            'advertiser_ids' => (array)$advertiserIds,
            'fields' => $fields
        ])['list'] ?? null;
    }

    public function getUserInfo() :?array
    {
        return $this->send('user/info');
    }

    private const array DEFAULT_METRICS_FOR_REPORT = ['advertiser_name', 'advertiser_id', 'currency',
        'spend', 'cpc', 'cpm', 'ctr', 'cost_per_1000_reached', 'frequency', 'total_complete_payment_rate',
        'total_online_consult_value', 'total_user_registration_value', 'total_web_event_add_to_cart_value',
        'total_on_web_order_value', 'total_initiate_checkout_value', 'total_add_billing_value', 'total_form_value',
        'total_on_web_subscribe_value', 'impressions', 'clicks', 'reach', 'conversion', 'engagements', 'follows',
        'likes', 'complete_payment', 'total_pageview', 'online_consult', 'user_registration', 'page_content_view_events',
        'web_event_add_to_cart', 'on_web_order', 'initiate_checkout', 'add_billing', 'page_event_search', 'form',
        'download_start', 'on_web_add_to_wishlist', 'on_web_subscribe', 'website_total_find_location',
        'website_total_schedule', 'custom_page_events'];

    public function getReport(
        string $advertiser_id,
        string $dataLevel,
        string $startDate,
        string $endDate,
        ?array $dimensions = [],
        int $page = 1,
        ?array $customMetrics = null
    ): ?array
    {
        [$defaultDimensions, $metrics] = match ($dataLevel) {
            'AUCTION_AD' => [
                ['ad_id'], ['ad_name', 'adgroup_id', 'adgroup_name', 'campaign_id', 'campaign_name']
            ],
            'AUCTION_ADGROUP' => [
                ['adgroup_id'], ['adgroup_name', 'campaign_id', 'campaign_name']
            ],
            'AUCTION_CAMPAIGN' => [
                ['campaign_id'], ['campaign_name']
            ],
            'AUCTION_ADVERTISER' => [
                ['advertiser_id'], []
            ]
        };

        $data = [
            'advertiser_id' => $advertiser_id,
            'report_type' => 'BASIC',
            'data_level' => $dataLevel,
            'dimensions' => array_merge( $defaultDimensions, $dimensions ?? [], ['stat_time_day']),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'page_size' => 1000,
            'metrics' => array_merge($metrics, $customMetrics ?: self::DEFAULT_METRICS_FOR_REPORT),
            'page' => $page,

        ];

        $res = $this->send('report/integrated/get', $data);

        $data = $res['list'] ?? [];

        if (!empty($res['page_info']['total_page']) && $page < $res['page_info']['total_page']) {
            return array_merge($data, $this->getReport($advertiser_id, $dataLevel, $startDate, $endDate, $dimensions, $page + 1, $customMetrics));
        }

        return $data;
    }

    public function send(string $url, ?array $body = null, string $method = 'get'): ?array
    {
        if (strtoupper($method) === 'GET') {
            $query = $body ? array_map(static fn($v) => !is_array($v) ? $v : json_encode($v), $body) : null;
            $body = null;
        }

        $res = $this->client
            ->send($method, trim($url, '/'), [
                'json' => $body,
                'query' => $query ?? []
            ]);

        $code = $res->json('code');


        if ($code !== 0) {
            Log::info("TikTok client: {$res->status()} $method $url", ['send' => $query ?? $body, 'response' => $res->json()]);
        }

        return $res->json('data');
    }
}
