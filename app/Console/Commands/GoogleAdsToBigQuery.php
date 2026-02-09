<?php

namespace App\Console\Commands;

use App\Facades\Currency;
use App\Facades\GoogleAds;
use App\Models\AdSource;
use App\Models\BigQuery\AdTable;
use Carbon\CarbonPeriod;
use Exception;
use Google\Ads\GoogleAds\V20\Enums\ConversionActionCategoryEnum\ConversionActionCategory;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Pelfox\LaravelBigQuery\Facades\BigQuery;
use RuntimeException;
use Throwable;

class GoogleAdsToBigQuery extends Command
{
    protected $signature = 'app:google-ads-to-big-query {--accountId=} {--date= : Examples: 2022-01-01...2022-01-22}';

    protected $description = 'Command description';

    private AdSource $row;

    private ?string $dayStart = null;
    private ?string $dayEnd = null;
    private ?string $dataDay = null;

    private ?string $currency = null;

    public function handle()
    {
        $periods = $this->getPeriods();

        foreach ($this->getSources() as $this->row) {
            $this->log('Розпочато завантаження даних');

            $this->checkBigQueryTable();

            foreach ($periods as $period) {
                $this->setDays($period);

                $accountsIds = [];
                $data        = [];

                foreach ($this->row->accounts as $value) {
                    try {
                        $insights = $this->getAdData($value['id']);
                    } catch (Exception $exception) {
                        $this->log('Аккаунт: "'.$value['name'].'" - помилка: '.$exception->getMessage());
                        continue;
                    }
                    array_push($data, ...$insights);
                    if (count($insights)) {
                        $accountsIds[] = $value['id'];
                    }
                    $this->log('Аккаунт: "'.$value['name'].($insights ? '" - завантажено рядків: '.count($insights) : '" - no data'));
                }
                if ($data) {
                    try {
                        $this->insertData($data, $accountsIds);
                        $this->log('Збережено до таблиці рядків: '.count($data));
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->log('🔴 Помилка завантаження даних');

                        return;
                    }
                }
            }
            $this->log('Data download complete');
        }
    }

    protected function getAdData($customerId): array
    {
        $level = $this->row->settings['level'] ?? 'campaign';
        $fields = self::DEFAULT_FIELDS_QUERY;
        $conversionFields = self::CONVERSION_FIELDS_QUERY;
        if ($this->row->settings['level'] === 'campaign') {
            $fields = array_merge($fields, self::CAMPAIGN_FIELDS_QUERY);
            $conversionFields = array_merge($conversionFields, self::CAMPAIGN_FIELDS_QUERY);
        }

        $customer = GoogleAds::search('SELECT customer.currency_code, customer.descriptive_name FROM customer', $customerId);
        $this->currency = $customer->current()->getCustomer()?->getCurrencyCode();
        $customerName = $customer->current()->getCustomer()?->getDescriptiveName();

        $query = 'SELECT ' . implode(', ', $fields) . ' FROM ' . $level
                 . ' WHERE segments.date BETWEEN "' . $this->dayStart . '" AND "' . $this->dayEnd .'"';
        $campaignData = GoogleAds::search($query, $customerId);

        $data = [];
        foreach ($campaignData as $row) {
            $this->dataDay = $row->getSegments()?->getDate();
            $campaignName = $row->getCampaign()?->getName();

            $data[$this->dataDay . $campaignName]['data'] = array_filter([
                'date' => $this->dataDay,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'currency' => $this->currency,
                'campaign_id' => $row->getCampaign()?->getId(),
                'campaign_name' => $campaignName,
                'average_cost' => $this->convertMicros($row->getMetrics()?->getAverageCost()),
                'average_cpc' => $this->convertMicros($row->getMetrics()?->getAverageCpc()),
                'average_cpe' => $this->convertMicros($row->getMetrics()?->getAverageCpe()),
                'average_cpm' => $this->convertMicros($row->getMetrics()?->getAverageCpm()),
                'average_cpv' => $this->convertMicros($row->getMetrics()?->getAverageCpv()),
                'cost_micros' => $this->convertMicros($row->getMetrics()?->getCostMicros()),
                'ctr' => ($ctr = $row->getMetrics()?->getCtr()) ? round($ctr,4) : null,
                'orders' => $row->getMetrics()?->getOrders(),
                'revenue_micros' => $this->convertMicros($row->getMetrics()?->getRevenueMicros()),
                'impressions' => $row->getMetrics()?->getImpressions(),
                'clicks' => $row->getMetrics()?->getClicks(),
                'invalid_clicks' => $row->getMetrics()?->getInvalidClicks(),
            ]);
        }

        $query = 'SELECT ' . implode(', ', $conversionFields) . ' FROM ' . $level
                 . ' WHERE segments.date BETWEEN "' . $this->dayStart . '" AND "' . $this->dayEnd . '"';
        $conversionData = GoogleAds::search($query, $customerId);

        foreach ($conversionData as $row) {
            $this->dataDay = $row->getSegments()?->getDate();
            $campaignName = $row->getCampaign()?->getName();

            if (empty($data[$this->dataDay . $campaignName])){
                continue;
            }

            $conversion = array_filter([
                'name' => $row->getSegments()?->getConversionActionName(),
                'category' => ($category = $row->getSegments()?->getConversionActionCategory())
                    ? ConversionActionCategory::name($category)
                    : null,

                'all_conversions' => $row->getMetrics()?->getAllConversions(),
                'all_conversions_value' => $this->convertMicros($row->getMetrics()?->getAllConversionsValue()),
                'value_per_all_conversions' => $this->convertMicros($row->getMetrics()?->getValuePerAllConversions()),

                'all_conversions_by_conversion_date' => $row->getMetrics()?->getAllConversionsByConversionDate(),
                'all_conversions_value_by_conversion_date' => $this->convertMicros($row->getMetrics()?->getAllConversionsValueByConversionDate()),
                'value_per_all_conversions_by_conversion_date' => $this->convertMicros($row->getMetrics()?->getValuePerAllConversionsByConversionDate()),

                'conversions' => $row->getMetrics()?->getConversions(),
                'conversions_value' => $this->convertMicros($row->getMetrics()?->getConversionsValue()),
                'value_per_conversion' => $this->convertMicros($row->getMetrics()?->getValuePerConversion()),

                'conversions_by_conversion_date' => $row->getMetrics()?->getConversionsByConversionDate(),
                'conversions_value_by_conversion_date' => $this->convertMicros($row->getMetrics()?->getConversionsValueByConversionDate()),
                'value_per_conversions_by_conversion_date' => $this->convertMicros($row->getMetrics()?->getValuePerConversionsByConversionDate()),
            ]);
            if (count($conversion) !== 2){
                $data[$this->dataDay . $campaignName]['data']['conversions'][] = $conversion;
            }
        }

        return array_values($data);
    }

    protected function convertMicros(?float $value): ?float
    {
        if (!$value) {
            return null;
        }

        $value = round($value / 1_000_000, 2);

        if ($this->row->currency){
            return Currency::convert($value, $this->currency, $this->row->currency, $this->dataDay);
        }

        return $value;
    }

    private function insertData(array $insertData, array $accountsIds = []): void
    {
        foreach ($accountsIds as $account) {
            AdTable::table($this->row)
                   ->where('date', '>=', $this->dayStart)
                   ->where('date', '<=', $this->dayEnd)
                   ->where('customer_id', $account)
                   ->delete();
        }

        sleep(1);

        $table = $this->row->getBigQueryTable();
        retry(3, static function () use ($table, $insertData) {
            $res = $table->insertRows($insertData);
            if (!$res->isSuccessful()){
                throw new RuntimeException(json_encode($res->failedRows(), JSON_THROW_ON_ERROR));
            }

            return true;
        }, 10000);
    }

    protected function getSources(): Collection|array
    {
        return AdSource::when($this->option('accountId'), static function (Builder $builder, $accountId) {
            return $builder->where('id', $accountId)->where('platform', 'gads');
        }, static function (Builder $builder) {
            return $builder->where('active', true)->where('platform', 'gads');
        })->get();
    }

    protected function setDays(?CarbonPeriod $period = null): void
    {
        $this->dayStart = $period?->getStartDate()->format('Y-m-d');
        $this->dayEnd   = $period?->getEndDate()?->format('Y-m-d');
    }

    private function checkBigQueryTable(): void
    {
        $table = $this->row->getBigQueryTable();
        if ( ! $table->exists()) {
            $fields = $this->getFieldsSchema();
            $table  = BigQuery::dataset(AdTable::DATASET)
                              ->createTable($this->row->getBigQueryTableName(), ['schema' => ['fields' => $fields]]);
            for ($i = 0; $i < 20; $i++) {
                if ($table->exists()) {
                    break;
                }

                sleep(1);
            }
            $this->log('Created a table in BigQuery "'.$this->row->getBigQueryTableName().'"');
        }
    }

    private function getFieldsSchema(): array
    {
        $fields = [];
        foreach (self::FIELDS_FOR_BIGQUERY_BY_TYPE as $type => $fieldsName) {
            if ($type !== 'RECORD'){
                $fields = [...$fields, ...$this->getFieldsSchemaByType($type, $fieldsName)];
            }else{
                $childFields = [];
                foreach ($fieldsName as $childType => $childValues) {
                    $childFields = [...$childFields, ...$this->getFieldsSchemaByType($childType, $childValues)];
                }
                $fields[] = [
                    'name' => 'conversions',
                    'type' => $type,
                    'mode' => 'REPEATED',
                    'fields' => $childFields
                ];
            }
        }
        return $fields;
    }

    protected function getFieldsSchemaByType(string $type, array $fields): array
    {
        $data = [];
        foreach ($fields as $name) {
            $data[] = [
                'name' => $name,
                'type' => $type,
                'mode' => 'NULLABLE'
            ];
        }
        return $data;
    }

    protected function getPeriods(): \Illuminate\Support\Collection
    {
        if ($date = $this->option('date')) {
            $days = explode('...', $date, 2);
        } else {
            $days = [now()->subDays(8), now()->subDay()];
        }

        return CarbonPeriod::between(...$days)->rangeChunks(4);
    }

    protected function log(string $message): void
    {
        $this->row->events()->create([
            'account_id' => $this->row->id,
            'day_start'  => $this->dayStart,
            'day_stop'   => $this->dayEnd,
            'message'    => $message
        ]);
    }

    private const array CAMPAIGN_FIELDS_QUERY = [
        'campaign.name', 'campaign.id'
    ];

    private const array DEFAULT_FIELDS_QUERY = [
        'segments.date',
        'metrics.average_cost', 'metrics.average_cpc', 'metrics.average_cpe', 'metrics.average_cpm', 'metrics.average_cpv',
        'metrics.clicks', 'metrics.cost_micros', 'metrics.ctr', 'metrics.impressions',
        'metrics.invalid_clicks', 'metrics.orders', 'metrics.revenue_micros'
    ];

    private const array CONVERSION_FIELDS_QUERY = [
        'segments.date',
        'segments.conversion_action_name', 'segments.conversion_action_category',
        'metrics.all_conversions_value', 'metrics.all_conversions', 'metrics.value_per_all_conversions',
        'metrics.all_conversions_by_conversion_date', 'metrics.conversions', 'metrics.conversions_by_conversion_date',
        'metrics.conversions_value', 'metrics.conversions_value_by_conversion_date', 'metrics.value_per_conversion',
        'metrics.all_conversions_value_by_conversion_date', 'metrics.value_per_conversions_by_conversion_date',
        'metrics.value_per_all_conversions_by_conversion_date'
    ];

    private const array FIELDS_FOR_BIGQUERY_BY_TYPE = [
        'STRING' => ['date', 'customer_name', 'currency', 'campaign_name'],

        'FLOAT' => ['average_cost', 'average_cpc', 'average_cpe', 'average_cpm', 'average_cpv',
            'cost_micros', 'ctr', 'orders', 'revenue_micros'],

        'INTEGER' => ['customer_id', 'campaign_id', 'impressions', 'clicks', 'invalid_clicks'],
        'RECORD' => [
            'STRING' => ['name', 'category'],
            'FLOAT' => [
                'all_conversions', 'all_conversions_value', 'value_per_all_conversions',
                'all_conversions_by_conversion_date', 'all_conversions_value_by_conversion_date', 'value_per_all_conversions_by_conversion_date',
                'conversions', 'conversions_value', 'value_per_conversion',
                'conversions_by_conversion_date', 'conversions_value_by_conversion_date', 'value_per_conversions_by_conversion_date'
            ],
        ]
    ];
}
