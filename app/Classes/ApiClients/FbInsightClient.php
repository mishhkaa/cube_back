<?php

namespace App\Classes\ApiClients;

use Carbon\CarbonPeriod;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FbInsightClient
{
    private PendingRequest $client;

    private const string API_URL = 'https://graph.facebook.com/v22.0/';

    public function __construct()
    {
        if (!$token = config('services.facebook.token')) {
            throw new RuntimeException('Set FACEBOOK_API_TOKEN in .env file');
        }
        $this->client = Http::withToken($token)
            ->baseUrl(self::API_URL);
    }

    public function pool(callable $callback): array
    {
        return $this->client->poolInherit($callback);
    }

    public function setToken(string $token): static
    {
        $this->client = Http::withToken($token)
            ->baseUrl(self::API_URL);
        return $this;
    }

    public function formattedId($actId)
    {
        return stripos($actId, 'act_') === false ? "act_$actId" : $actId;
    }

    public function checkAccount($actId): bool
    {
        $actId = $this->formattedId($actId);
        $res = $this->client->get((string)$actId, ['fields' => 'name']);

        if ($res->successful()) {
            return true;
        }
        if (($code = $res->json('error.code')) && $code === 100) {
            return false;
        }
        return true;
    }

    public function getPageLeadGenForms($pageId): array
    {
        return $this->send($pageId.'/leadgen_forms');
    }

    public function getInsights($actId, $date_from, $date_to, array $fields = [], string $level = 'account', bool $byDays = false): array
    {
        $actId = $this->formattedId($actId);

        $query = [
            'fields' => implode(',', $fields),
            'level' => $level,
            'time_range' => [
                'since' => Carbon::parse($date_from)->format('Y-m-d'),
                'until' => Carbon::parse($date_to)->format('Y-m-d')
            ],
//            'breakdowns' => [
//                'country'
//            ]
        ];
        if ($byDays) {
            $query += [
                'time_ranges' => collect(CarbonPeriod::between($date_from, $date_to))
                    ->map(fn($date) => $this->getDays($date))->toArray()
            ];
        } else {
            $query += [
                'time_range' => $this->getDays(Carbon::parse($date_from), Carbon::parse($date_to))
            ];
        }

        return $this->send("$actId/insights/", $query);
    }

    protected int $retries = 0;

    public function getInsightsAsync(
        $actId,
        $date_from,
        $date_to,
        array $fields = [],
        string $level = 'account',
        ?array $settings = null,
        bool $byDays = false
    ): array {
        if ($this->retries === 3) {
            return [];
        }

        $actId = $this->formattedId($actId);
        $query = [
            'fields' => implode(',', $fields),
            'level' => $level,
        ];
        if ($byDays) {
            $query += [
                'time_ranges' => collect(CarbonPeriod::between($date_from, $date_to))
                    ->map(fn($date) => $this->getDays($date))->toArray()
            ];
        } else {
            $query += [
                'time_range' => $this->getDays(Carbon::parse($date_from), Carbon::parse($date_to))
            ];
        }
        if (!empty($settings['attributes'])) {
            $query['action_attribution_windows'] = $settings['attributes'];
        }
        if (!empty($settings['breakdowns'])) {
            $query['breakdowns'] = $settings['breakdowns'];
            if (in_array('platform_position', $settings['breakdowns'], true)) {
                $query['breakdowns'][] = 'publisher_platform';
            }
        }

        $res = $this->client->post("/$actId/insights/", $query);

        $reportId = $res->json('report_run_id');
        $error = $res->json('error');

        if ($error) {
            Log::error("FbInsightClient: API error for account {$actId}", [
                'error' => $error,
                'response' => $res->json()
            ]);
        }

        if (!$reportId) {
            Log::error("FbInsightClient: No report_run_id for account {$actId}", [
                'response' => $res->json(),
                'body' => $res->body()
            ]);
            throw new RuntimeException($res->body());
        }

        for ($i = 0; $i < 120; $i++) {
            $res = $this->client->get($reportId)->json();
            if (!empty($res['async_status'])) {
                if ($res['async_status'] === 'Job Completed') {
                    $this->retries = 0;
                    $insightsData = $this->send("$reportId/insights");
                    return $insightsData;
                }
            }
            sleep(3);
        }
        $this->retries++;
        Log::error("FbInsightClient: Report not finished after 600 seconds for account {$actId}", [
            'report_id' => $reportId,
            'retries' => $this->retries,
            'last_response' => $res ?? []
        ]);
        return $this->getInsightsAsync($actId, $date_from, $date_to, $fields, $level, $settings, $byDays);
    }

    protected function getDays(Carbon|string $date, ?Carbon $dateTo = null): array
    {
        $day = Carbon::parse($date)->format('Y-m-d');
        return [
            'since' => $day,
            'until' => $dateTo?->format('Y-m-d') ?: $day
        ];
    }

    public function getAdCreatives($id, $fields = []): array
    {
        $query = [
            'fields' => implode(',', $fields),
            'thumbnail_height' => 300,
            'thumbnail_width' => 300
        ];

        return $this->send("$id/adcreatives", $query);
    }

    public function getAccounts(array $fields = ['id', 'name', 'account_status']): array
    {
        return $this->send('me/adaccounts', ['fields' => implode(',', $fields), 'limit' => 500]);
    }

    public function debugToken(string $token): array
    {
        return Http::baseUrl(self::API_URL)
            ->withToken($token)
            ->get('debug_token', [
                'input_token' => $token
            ])->json('data') ?: [];
    }

    public function getAccessToken(string $code): ?string
    {
        return $this->client->get('oauth/access_token', [
            'client_id' => config('services.facebook.app.id'),
            'redirect_uri' => config('services.facebook.app.redirect_url'),
            'client_secret' => config('services.facebook.app.secret'),
            'code' => $code,
        ])->json('access_token');
    }

    public function send(string $url, array $query = [], bool $rootData = false): array
    {
        $res = $this->client->get($url, $query);
        
        if ($error = $res->json('error')) {
            Log::error("FbInsightClient:send - API error", [
                'url' => $url,
                'status' => $res->status(),
                'error' => $error
            ]);
            throw new RuntimeException("Facebook API error {$res->status()}: {$error['message']}");
        }

        if ($rootData) {
            return $res->json();
        }
        
        $data = $res->json('data', []);

        if ($next = $res->json('paging.next')) {
//            $url = parse_url(str_replace(self::API_URL, '', $next), PHP_URL_PATH);
            parse_str(parse_url($next, PHP_URL_QUERY), $newQuery);
            $query = array_merge($query, $newQuery);

            return array_merge($data, $this->send($url, $query));
        }

        return $data;
    }

    public array $fields = [
        "spend",
        'account_currency',
        'campaign_id',
        'actions',
        'action_values',
        'cost_per_action_type',
        'purchase_roas',
        'clicks',
        'cpc',
        'impressions',
        'ctr',
        'cpm'
    ];

    public static array $costFields = [
        'action_values',
        'conversion_values',
        'converted_product_value',
        'cost_per_action_type',
        'cost_per_conversion',
        'cost_per_estimated_ad_recallers',
        'cost_per_inline_link_click',
        'cost_per_inline_post_engagement',
        'cost_per_outbound_click',
        'cost_per_thruplay',
        'cost_per_unique_action_type',
        'cost_per_unique_click',
        'cost_per_unique_inline_link_click',
        'cost_per_unique_outbound_click',
        'cpc',
        'cpm',
        'cpp',
        'spend',
    ];
}
