<?php

namespace App\Console\Commands;

use App\Classes\ApiClients\FbInsightClient;
use App\Facades\Currency;
use App\Facades\FbInsight;
use App\Models\BigQuery\Pdl;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\Cloud\BigQuery\Dataset;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pelfox\LaravelBigQuery\Facades\BigQuery;
use Throwable;

class PdlBigQuery extends Command
{
    protected $signature = 'bigquery:pdl {--date=}';
    protected $description = '';

    private Dataset $dataset;

    protected string $domain;
    protected string $day;

    private string $apiKey;

    public function handle(): void
    {
        $this->dataset = BigQuery::dataset(Pdl::DATASET);

        $domains = collect(Setting::get('pdl_domains') ?: [])
            ->filter(fn($v) => $v['active']);

        foreach ($this->getDays() as $this->day) {
            $data = [];
            foreach ($domains as $params) {
                $this->domain = $params['domain'];
                $this->apiKey = $params['api_key'];

                $data = [...$data, ...$this->getData($params)];
            }
            $this->insertData($data);
        }
    }


    protected function getData(array $params): array
    {
        $binomData = $this->getDataBinom();

        if (!$binomData) {
            return [];
        }

        $costData = [
            'ga' => !empty($params['ga4']) ? $this->getGoogle4Data($params['ga4']) : 0,
            'fb' => !empty($params['fb']) ? $this->getFacebookData($params['fb']) : [],
            'sms' => Currency::convert($this->getSMSCost(), 'uah', 'usd')
        ];

        return $this->parseData($binomData, $costData);
    }

    protected function getDays(): Collection
    {
        $days = CarbonPeriod::days(7);
        if ($date = $this->option('date')) {
            $days->between(...explode('...', $date));
        } else {
            $days->between(now()->subDays(2)->subHours(3), now()->subHours(3));
        }
        return collect($days)->map(fn(Carbon $carbon) => $carbon->format('Y-m-d'));
    }

    protected function getGoogle4Data($id): float|int
    {
        $client = new BetaAnalyticsDataClient([
            'credentials' => File::json(resource_path('keys/core-dominion-260616-fe416c814fff.json'))
        ]);

        $data = [
            'property' => 'properties/'.$id,
            'dateRanges' => [
                new DateRange([
                    'start_date' => $this->day,
                    'end_date' => $this->day,
                ]),
            ],
            'dimensions' => [new Dimension(['name' => 'sessionCampaignName'])],
            'metrics' => [new Metric(['name' => 'advertiserAdCost',])],
        ];
        $budget = 0;
        try {
            $response = $client->runReport(new RunReportRequest($data));

            foreach ($response->getRows() as $row) {
                foreach ($row->getMetricValues() as $dimensionValue) {
                    $budget += (float)$dimensionValue->getValue();
                }
            }
            return $budget;
        } catch (Throwable $exception) {
            report($exception);
            return 0;
        }
    }

    protected function getSMSCost(): float|int
    {
        $cost = Http::get("https://{$this->domain}/mapi/cost.php?date={$this->day}")->body();
        return (float)$cost ?: 0;
    }

    protected function parseData(array $binom, array $costData): array
    {
        $data = [];
        $facebookIs = true;
        $googleIs = true;
        $setSMSCost = false;
        foreach ($binom as $item) {
            $row = [
                'spend' => 0,
                'cr' => !$item['purchase'] || !$item['clicks'] || !$item['lp_clicks'] || !($item['clicks'] - $item['lp_clicks'])
                    ? 0 : $item['purchase'] / ($item['clicks'] - $item['lp_clicks']) * 100,
                'cpm' => 0,
                'ctr' => 0,
                'clicks' => $item['clicks'] ?? 0,
                'purchase' => $item['purchase'] ?? 0,
                'leads' => $item['leads'] ?? 0,
                'item' => $item['item'],
                'campaign' => $item['campaign'],
                'domain' => $this->domain,
                'channel' => $item['channel'],
                'revenue' => $item['revenue'] ?? 0,
                'date' => $this->day,
                'cpc' => 0,
                'partner' => $item['partner'] ?? '',
                'times' => (int)($item['times'] ? ($item['times'] / $item['purchase']) : 0),
                'lp_clicks' => $item['lp_clicks'] ?? 0,
                'lp_ctr' => $item['lp_ctr'] ?? 0
            ];
            if (!$item['item']) {
                if ($item['channel'] == 'Facebook' && $facebookIs) {
                    $facebookIs = false;
                    $row['spend'] = $costData['fb']['spend'] ?? 0;
                    $row['ctr'] = $costData['fb']['ctr'] ?? 0;
                    $row['cpm'] = $costData['fb']['cpm'] ?? 0;
                    $row['cpc'] = $costData['fb']['cpc'] ?? 0;
                }
                if ($item['channel'] == 'Google' && $googleIs) {
                    $googleIs = false;
                    $row['spend'] = $costData['ga'];
                }
                if ($item['channel'] == 'SMS' && !$setSMSCost) {
                    $setSMSCost = true;
                    $row['spend'] = $costData['sms'];
                }
            }
            $data[]['data'] = $row;
        }
        return $data;
    }

    public function insertData($data): void
    {
        if (!$data) {
            return;
        }
        $tableBq = $this->dataset->table(Pdl::TABLE);
        if (!$tableBq->exists()) {
            $tableBq = $this->dataset->createTable(Pdl::TABLE, ['schema' => ['fields' => $this->tableSchema]]);
        }

        Pdl::query()->where('date', $this->day)->delete();

        $res = $tableBq->insertRows($data);
        if (!$res->isSuccessful()) {
            foreach ($res->failedRows() as $row) {
                Log::info('BigQuery add data ', $row);
            }
        }
    }

    protected array $defaultDataRow = [
        'date' => '',
        'domain' => '',
        'channel' => '',
        'offer' => '',
        'campaign' => '',
        'revenue' => 0,
        'leads' => 0,
        'partner' => '',
        'times' => 0,
        'purchase' => 0,
        'clicks' => 0,
        'lp_clicks' => 0,
        'spend' => 0,
        'cr' => 0,
        'cpm' => 0,
        'ctr' => 0,
        'cpc' => 0,
        'lp_ctr' => 0
    ];


    protected function getDataBinom(): array
    {
        $campaigns = $this->requestToBinom([
            'page' => 'Campaigns',
            'date_e' => $this->day,
            'date_s' => $this->day,
            'date' => 12
        ]);
        $data = [];
        foreach ($campaigns as $campaign) {
            $statsCampaign = $this->requestToBinom([
                'page' => 'Stats',
                'camp_id' => $campaign['id'],
                'group1' => 3,
                'group2' => 1,
                'group3' => 1,
                'date' => 12,
                'date_s' => $this->day,
                'date_e' => $this->day,
                'timezone' => '%202:00'
            ]);

            $channel = $this->getNameChannel($campaign['name']);

            $data[$campaign['id']] = array_merge($campaign, $this->defaultDataRow, [
                'campaign' => $campaign['name'],
                'channel' => $channel,
            ]);

            foreach ($statsCampaign as $item) {
                $offerId = preg_replace('/.*id:(\d+)\).*/', '$1', $item['name']);
                preg_match('/^(.*)\s-\s(.*)\s\(id:\d+\)$/', $item['name'], $partnerOffer);

                $offerData = array_merge($item, $this->defaultDataRow, [
                    'channel' => $channel,
                    'campaign' => $campaign['campaign'],
                    'offer' => $partnerOffer[2] ?? '',
                    'partner' => $partnerOffer[1] ?? '',

                ]);
                $data["{$campaign['id']}-{$offerId}"] = $offerData;
            }
        }
        $conversions = $this->requestToBinom([
            'page' => 'Conversions',
            'date_e' => $this->day,
            'date_s' => $this->day,
            'date' => 12,
            'num_page' => 1,
            'Offer_fltr' => 'All',
            'GEO' => 'All',
            'camp_id' => 'All',
            'val_page' => 'All',
        ]);

        foreach ($conversions as $conversion) {
            foreach ([$conversion['camp_id'], "{$conversion['camp_id']}-{$conversion['offer']}"] as $key) {
                $isPaid = in_array($conversion['status'], ['paid', 'approve']);
                $times = $isPaid ? (strtotime($conversion['time']) - strtotime($conversion['click_time'])) : 0;

                if (!empty($data[$key])) {
                    if ($isPaid) {
                        $data[$key]['purchase']++;
                    } else {
                        $data[$key]['leads']++;
                    }
                    $data[$key]['revenue'] = (float)$conversion['pay'];
                    $data[$key]['times'] = $times;
                    continue;
                }

                $data[$key] = array_merge($this->defaultDataRow, [
                    'channel' => $this->getNameChannel($conversion['camp_name']),
                    'offer' => preg_replace('/^(.*) \(\d+\)$/', '$1', $conversion['offer_name']),
                    'revenue' => (float)$conversion['pay'],
                    'leads' => $isPaid ? 0 : 1,
                    'purchase' => $isPaid ? 1 : 0,
                    'times' => $times,
                    'campaign' => preg_replace('/^(.*) \(\d+\)$/', '$1', $conversion['camp_name']),
                    'partner' => $this->getPartnerByOfferID(preg_replace('/^.* \((\d+)\)$/', '$1', $conversion['offer_name']))
                ]);
            }
        }
        return $data;
    }

    protected function getPartnerByOfferID($id)
    {
        static $offers = null;

        if (!$offers) {
            $offers = $this->requestToBinom(['page' => 'Offers']);
        }

        foreach ($offers as $offer) {
            if ($offer['id'] == $id) {
                return $offer['network_name'] ?? '';
            }
        }
        return '';
    }

    public function getNameChannel(string $campaignName): string
    {
        return match (true) {
            stripos($campaignName, 'Single') !== false => 'Single offer',
            stripos($campaignName, 'Facebook ads') !== false => 'Facebook',
            stripos($campaignName, 'Google ads') !== false => 'Google',
            stripos($campaignName, 'Mailing List') !== false => 'Mailing',
            stripos($campaignName, 'SMS') !== false => 'SMS',
            default => 'Organic'
        };
    }

    public function requestToBinom(array $query = []): array
    {
        $url = 'https://'.$this->domain.'/admin.php';
        $query['api_key'] = $this->apiKey;
        $res = Http::get($url, $query)->json();
        return is_array($res) && !isset($res['error']) ? $res : [];
    }

    protected function getFacebookData($act): array
    {
        $res = FbInsight::getInsights($act, $this->day, $this->day, ['spend', 'cpm', 'cpc', 'ctr', 'account_currency'])[0] ?? [];
        $currency = $res['account_currency'] ?? '';
        $costFields = FbInsightClient::$costFields;
        foreach ($res as $key => $item) {
            if (in_array($key, $costFields)) {
                $res[$key] = Currency::convert($item, $currency, 'usd', $this->day);
            }
        }
        return $res;
    }

    protected array $tableSchema = [
        [
            'name' => 'date',
            'type' => 'DATE',
            'mode' => 'NULLABLE',
        ],
        [
            'name' => 'domain',
            'type' => 'STRING',
            'mode' => 'NULLABLE',
        ],
        [
            'name' => 'channel',
            'type' => 'STRING',
            'mode' => 'NULLABLE',
        ],
        [
            'name' => 'offer',
            'type' => 'STRING',
            'mode' => 'NULLABLE',
        ],
        [
            'name' => 'spend',
            'type' => 'FLOAT',
            'mode' => 'NULLABLE',
        ],
        [
            'name' => 'cr',
            'type' => 'FLOAT',
            'mode' => 'NULLABLE',
        ],
        [
            'name' => 'cpm',
            'type' => 'FLOAT',
            'mode' => 'NULLABLE',
        ],
        [
            'name' => 'ctr',
            'type' => 'FLOAT',
            'mode' => 'NULLABLE',
        ],
        [
            'name' => 'clicks',
            'type' => 'INTEGER',
            'mode' => 'NULLABLE',
        ],
        [
            'name' => 'purchase',
            'type' => 'INTEGER',
            'mode' => 'NULLABLE',
        ],
        [
            'name' => 'revenue',
            'type' => 'FLOAT',
            'mode' => 'NULLABLE',
        ],
        [
            'name' => 'cpc',
            'type' => 'FLOAT',
            'mode' => 'NULLABLE',
        ],
        [
            'name' => 'lp_clicks',
            'type' => 'INTEGER',
            'mode' => 'NULLABLE',
        ],
        [
            'name' => 'lp_ctr',
            'type' => 'FLOAT',
            'mode' => 'NULLABLE',
        ],
        [
            'name' => 'leads',
            'type' => 'INTEGER',
            'mode' => 'NULLABLE',
        ],
        [
            'name' => 'times',
            'type' => 'INTEGER',
            'mode' => 'NULLABLE',
        ],
        [
            'name' => 'campaign',
            'type' => 'STRING',
            'mode' => 'NULLABLE',
        ],
        [
            'name' => 'partner',
            'type' => 'STRING',
            'mode' => 'NULLABLE',
        ],
    ];
}
