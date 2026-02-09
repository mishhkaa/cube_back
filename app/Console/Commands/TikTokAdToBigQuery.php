<?php

namespace App\Console\Commands;

use AllowDynamicProperties;
use App\Facades\Currency;
use App\Facades\TikTok;
use App\Models\AdSource;
use App\Models\BigQuery\AdTable;
use App\Models\User;
use Carbon\CarbonPeriod;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Pelfox\LaravelBigQuery\Facades\BigQuery;
use RuntimeException;
use Throwable;

class TikTokAdToBigQuery extends Command
{
    protected $signature = 'app:tik-tok-ad-to-big-query {--accountId=} {--date= : Examples: 2022-01-01...2022-01-22}';

    protected $description = 'Command description';

    private AdSource $row;

    private ?string $dayStart = null;
    private ?string $dayEnd = null;

    public function handle(): void
    {
        $periods = $this->getPeriods();

        foreach ($this->getSources() as $this->row) {
            $this->log('Розпочато завантаження даних');

            if (!$this->row->user_id || !($user = User::find($this->row->user_id)) || !$user->fb_access_token){
                $this->log('Користувач не має доступу до TikTok');
                continue;
            }

            TikTok::setAccessToken($user->tiktok_access_token);

            $this->checkBigQueryTable();

            foreach ($periods as $period) {
                $this->setDays($period);

                $accountsIds = [];
                $data = [];

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
                    $insertData = $this->dataProcessing($data);
                    try {
                        $this->insertData($insertData, $accountsIds);
                        $this->log('Збережено до таблиці рядків: '. count($insertData));
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

    private function insertData(array $insertData, array $accountsIds = []): void
    {
        foreach ($accountsIds as $account) {
            AdTable::table($this->row)
                ->where('date', '>=', $this->dayStart)
                ->where('date', '<=', $this->dayEnd)
                ->where('advertiser_id', $account)
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

    private function dataProcessing(array $data): array
    {
        $insertData = [];

        foreach ($data as $item) {
            if ($this->row->currency) {
                $prevCurrency = $item['currency'];
                $item['currency'] = $this->row->currency;
                foreach ($item as $k => $v) {
                    if (in_array($k, self::FIELDS_FOR_BIGQUERY_BY_TYPE['FLOAT'], true)) {
                        $item[$k] = Currency::convert($v, $prevCurrency, $item['currency'], $item['date']);
                    }
                }
            }

            $insertData[] = ['data' => $item];
        }

        return $insertData;
    }

    private function getAdData(string $advertiserId): array
    {
        $resData = TikTok::getReport($advertiserId,
            $this->row->settings['data_level'] ?? 'AUCTION_ADVERTISER',
            $this->dayStart,
            $this->dayEnd,
            $this->row->settings['dimensions'] ?? [],
        );

        $data = [];
        foreach ($resData as $item) {
            $allData = array_merge($item['metrics'], $item['dimensions']);
            $allData['date'] = explode(' ', $allData['stat_time_day'])[0];
            unset($allData['stat_time_day']);

            foreach ($allData as $k => $v) {
                $value = match (true){
                    in_array($k, self::FIELDS_FOR_BIGQUERY_BY_TYPE['FLOAT'], true) => (float)$v,
                    in_array($k, self::FIELDS_FOR_BIGQUERY_BY_TYPE['INTEGER'], true) => (int)$v,
                    default => $v
                };
                if (!$value) {
                    unset($allData[$k]);
                    continue;
                }

                $allData[$k] = $value;
            }
            $data[] = $allData;
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

    private function checkBigQueryTable(): void
    {
        $table = $this->row->getBigQueryTable();
        if (!$table->exists()) {
            $fields = $this->getFieldsSchema();
            $table = BigQuery::dataset(AdTable::DATASET)
                ->createTable(
                    $this->row->getBigQueryTableName(),
                    ['schema' => ['fields' => $fields]]
                );
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
        $fields = [[
            'name' => 'date',
            'type' => 'DATE',
            'mode' => 'NULLABLE'
        ]];
        foreach (self::FIELDS_FOR_BIGQUERY_BY_TYPE as $type => $fieldsName) {
            foreach ($fieldsName as $name) {
                $fields[] = [
                    'name' => $name,
                    'type' => $type,
                    'mode' => 'NULLABLE'
                ];
            }
        }
        return $fields;
    }

    protected function getSources(): Collection|array
    {
        return AdSource::when($this->option('accountId'), static function (Builder $builder, $accountId) {
            return $builder->where('id', $accountId)->where('platform', 'tiktok');
        }, static function (Builder $builder) {
            return $builder->where('active', true)->where('platform', 'tiktok');
        })->get();
    }

    protected function log(string $message): void
    {
        $this->row->events()->create([
            'account_id' => $this->row->id,
            'day_start' => $this->dayStart,
            'day_stop' => $this->dayEnd,
            'message' => $message
        ]);
    }

    protected function setDays(?CarbonPeriod $period = null): void
    {
        $this->dayStart = $period?->getStartDate()->format('Y-m-d');
        $this->dayEnd = $period?->getEndDate()?->format('Y-m-d');
    }


    public const array FIELDS_FOR_BIGQUERY_BY_TYPE = [
        'STRING' => ['advertiser_id', 'advertiser_name', 'campaign_name', 'campaign_id', 'ad_name', 'ad_id',
            'adgroup_name', 'adgroup_id', 'currency', 'country_code', 'search_terms'],

        'FLOAT' => ['spend', 'cpc', 'cpm', 'ctr', 'cost_per_1000_reached', 'frequency', 'total_complete_payment_rate', 'total_online_consult_value',
            'total_user_registration_value', 'total_web_event_add_to_cart_value', 'total_on_web_order_value', 'total_initiate_checkout_value',
            'total_add_billing_value', 'total_form_value', 'total_on_web_subscribe_value'],

        'INTEGER' => ['impressions', 'clicks', 'reach', 'conversion', 'engagements', 'follows', 'likes', 'complete_payment', 'total_pageview',
            'online_consult', 'user_registration', 'page_content_view_events', 'web_event_add_to_cart', 'on_web_order', 'initiate_checkout',
            'add_billing', 'page_event_search', 'form', 'download_start', 'on_web_add_to_wishlist', 'on_web_subscribe', 'website_total_find_location',
            'website_total_schedule', 'custom_page_events'],
    ];
}
