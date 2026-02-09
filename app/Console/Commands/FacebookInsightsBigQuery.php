<?php

namespace App\Console\Commands;

use App\Classes\ApiClients\FbInsightClient;
use App\Facades\Currency;
use App\Facades\FbInsight;
use App\Models\AdSource;
use App\Models\BigQuery\AdTable;
use App\Models\User;
use Carbon\CarbonPeriod;
use Exception;
use Google\Cloud\BigQuery\Dataset;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Pelfox\LaravelBigQuery\Facades\BigQuery;
use RuntimeException;
use Throwable;


class FacebookInsightsBigQuery extends Command
{
    protected $signature = 'bigquery:fb-insights {--accountId=} {--date= : Examples: 2022-01-01...2022-01-22}';
    protected $description = '';

    protected Dataset $dataset;

    protected array $tableSchema = [];
    protected array $recordsFields = [];
    protected ?string $dayStart = null;
    protected ?string $dayEnd = null;

    protected ?string $currency = null;
    protected array $adCreativesData = [];
    private AdSource $row;

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        $this->dataset = BigQuery::dataset(AdTable::DATASET);

        $daysSliding = $this->getPeriods();

        foreach ($this->getSources() as $this->row) {
            $this->log('Starting to download data');

            if (!$this->checkAccounts()) {
                continue;
            }

            if (!$this->row->user_id || !($user = User::find($this->row->user_id)) || !$user->fb_access_token){
                $this->log('User not found or no access to facebook');
                continue;
            }
            FbInsight::setToken($user->fb_access_token);

            $this->checkBigQueryTable();

            foreach ($daysSliding as $period) {
                $this->setDays($period);
                $this->log('Start loading data');
                $accountsWithData = [];
                $data = [];
                foreach ($this->row->accounts as $value) {
                    try {
                        $this->log('Account: "'.$value['name'].'" (ID: '.$value['id'].') - starting data fetch...');
                        $insights = $this->getFbData($value['id']);
                        $this->log('Account: "'.$value['name'].'" - getFbData returned '.count($insights).' records');
                    } catch (Exception $exception) {
                        $this->log('Account: "'.$value['name'].'" - error: '.$exception->getMessage());
                        $this->log('Account: "'.$value['name'].'" - error trace: '.$exception->getTraceAsString());
                        continue;
                    } catch (Throwable $exception) {
                        $this->log('Account: "'.$value['name'].'" - throwable error: '.$exception->getMessage());
                        continue;
                    }
                    array_push($data, ...$insights);
                    if (count($insights)) {
                        $accountsWithData[] = $value['id'];
                    }
                    $this->log('Account: "'.$value['name'].($insights ? '" - term loaded: '.count($insights) : '" - no data'));
                }
                if ($data) {
                    $insertData = $this->formatterData($data);
                    $this->checkFieldsUpdateSchemaTable();
                    try {
                        $this->insertData($insertData, $accountsWithData);
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->log('🔴 Error to saved data');
                        return;
                    }
                }
                $this->setDays();
            }
            $this->log('Data download complete');
        }
    }

    protected function setDays(?CarbonPeriod $period = null): void
    {
        $this->dayStart = $period?->getStartDate()->format('Y-m-d');
        $this->dayEnd = $period?->getEndDate()?->format('Y-m-d');
    }

    protected function getSources(): Collection|array
    {
        return AdSource::query()
            ->when($this->option('accountId'), function (Builder $builder, $accountId) {
                return $builder->where('id', $accountId)->where('platform', 'fb');
            }, function (Builder $builder) {
                return $builder->where('active', true)->where('platform', 'fb');
            })->get();
    }

    protected function getPeriods(): \Illuminate\Support\Collection
    {
        if ($date = $this->option('date')) {
            $days = explode('...', $date, 2);
        } else {
            $days = [now()->subDays(8), now()->subDay()];
        }

        return CarbonPeriod::between(...$days)->rangeChunks(7);
    }


    public function checkAccounts(): bool
    {
        $accounts = $this->row->accounts ?: [];
        foreach ($accounts as $key => $account) {
            if (!FbInsight::checkAccount($account['id'])) {
                unset($accounts[$key]);
            }
        }

//        $this->row->accounts = $accounts;
//        $this->row->active = (bool)$accounts;
//
//        $this->row->save();

        if (!$this->row->active) {
            $this->log('Data loading stopped: no accounts');
            return false;
        }
        return true;
    }

    protected function formatterData($data): array
    {
        $this->recordsFields = [];
        $bqData = [];
        foreach ($data as $item) {
            $item['date'] = $item['date_start'];
            unset($item['date_start'], $item['date_stop']);
            $currency = $item['account_currency'];
            if ($this->currency) {
                $item['account_currency'] = $this->currency;
            }
            $bqItem = [];
            foreach ($item as $k => $v) {
                if (is_array($v)) {
                    $bqItem[$k] = [];
                    foreach (array_column($v, 'value', 'action_type') as $i => $l) {
                        $bqItem[$k][str_replace(['.', '-'], ['__', '_'], $i)] = in_array($k, FbInsightClient::$costFields, true)
                            ? $this->convertCurrency($l, $currency, $item['date']) : $l;
                    }
                    if ($attributes = $this->row->settings['attributes'] ?? []) {
                        foreach ($attributes as $attribute) {
                            foreach (array_column($v, $attribute, 'action_type') as $i => $l) {
                                $bqItem[$k][str_replace(['.', '-'], ['__', '_'], $i).'_'.$attribute] = in_array($k, FbInsightClient::$costFields, true)
                                    ? $this->convertCurrency($l, $currency, $item['date']) : $l;
                            }
                        }
                    }
                    $this->recordsFields[$k] = array_unique(array_merge($this->recordsFields[$k] ?? [], array_keys($bqItem[$k])));
                } else {
                    $bqItem[$k] = in_array($k, FbInsightClient::$costFields, true) ? $this->convertCurrency($v, $currency, $item['date']) : $v;
                }
            }
            $bqData[] = ['data' => $bqItem];
        }
        return $bqData;
    }

    protected function checkBigQueryTable(): void
    {
        $table = $this->dataset->table($this->row->getBigQueryTableName());
        if (!$table->exists()) {
            $table = $this->dataset->createTable($this->row->getBigQueryTableName());
            for ($i = 0; $i < 20; $i++) {
                if ($table->exists()) {
                    break;
                }

                sleep(1);
            }
            $this->log('Created a table in BigQuery "'.$this->row->getBigQueryTableName().'"');
            $this->tableSchema = self::DEFAULT_SCHEMA;
        } else {
            $this->tableSchema = $table->info()['schema']['fields'] ?? [];
            if (!$this->tableSchema) {
                $this->tableSchema = self::DEFAULT_SCHEMA;
            }
        }
    }


    protected function convertCurrency($value, $fromCurrency, $day): float
    {
        if (!$this->currency) {
            return $value;
        }
        return Currency::convert($value, $fromCurrency, $this->currency, $day);
    }

    protected function checkFieldsUpdateSchemaTable(): void
    {
        $table = $this->dataset->table($this->row->getBigQueryTableName());
        $updating = false;
        $this->tableSchema = array_map(function ($v) use (&$updating) {
            if ($v['type'] !== 'RECORD') {
                return $v;
            }
            if (!empty($this->recordsFields[$v['name']]) && !empty($v['fields'])) {
                $fields = array_column($v['fields'], 'name');
                $type = in_array($v['name'], ['actions', 'conversions']) ? 'INTEGER' : 'FLOAT';
                foreach ($this->recordsFields[$v['name']] as $field) {
                    if (!in_array($field, $fields, true)) {
                        $updating = true;
                        $v['fields'][] = [
                            'name' => $field,
                            'type' => $type,
                            'mode' => 'NULLABLE'
                        ];
                    }
                }
            }
            return $v;
        }, $this->tableSchema);
        if (empty($table->info()['schema']['fields'])) {
            $updating = true;
        }
        if ($updating) {
            $table->update(['schema' => ['fields' => $this->tableSchema]]);
            $this->log('Table schema updated');
            sleep(10);
        }
    }

    /**
     * @throws Throwable
     */
    protected function insertData($data, $accounts): void
    {
        if (empty($data)) {
            return;
        }

        $count = count($data);
        $tableName = $this->row->getBigQueryTableName();
        $fullTableName = AdTable::DATASET . '.' . $tableName;
        $tempTableName = $tableName . '_temp_' . time();

        // Try to delete old data first
        $deleteFailed = false;
        foreach ($accounts as $account) {
            try {
                AdTable::table($this->row)
                    ->where('date', '>=', $this->dayStart)
                    ->where('date', '<=', $this->dayEnd)
                    ->where('account_id', (int)str_replace('act_', '', $account))
                    ->delete();
            } catch (Throwable $e) {
                // If delete fails due to streaming buffer, use MERGE instead
                if (str_contains($e->getMessage(), 'streaming buffer')) {
                    $deleteFailed = true;
                    $this->log("Warning: Could not delete old data for account {$account} (streaming buffer), will use MERGE");
                } else {
                    throw $e;
                }
            }
        }

        sleep(1);

        $table = $this->dataset->table($tableName);

        // If delete failed, use MERGE to avoid duplicates
        if ($deleteFailed) {
            $this->mergeData($data, $fullTableName, $tempTableName, $count);
        } else {
            // Normal insert if delete succeeded
            $row = $data[0]['data'];
            retry(6, static function () use ($row, $table) {
                $res = $table->insertRow($row);
                if (!$res->isSuccessful()){
                    throw new RuntimeException(json_encode($res->failedRows(), JSON_THROW_ON_ERROR));
                }
            }, 3000);

            unset($data[0]);
            if ($data) {
                retry(3, static function () use ($table, $data) {
                    $res = $table->insertRows($data);
                    if (!$res->isSuccessful()){
                        throw new RuntimeException(json_encode($res->failedRows(), JSON_THROW_ON_ERROR));
                    }
                }, 10000);
            }

            $this->log('Saved to string table: '.$count);
        }
    }

    protected function mergeData($data, string $fullTableName, string $tempTableName, int $count): void
    {
        // Ensure table schema is set
        if (empty($this->tableSchema)) {
            $this->tableSchema = self::DEFAULT_SCHEMA;
        }

        // Create temporary table
        $tempTable = $this->dataset->table($tempTableName);
        $tempTable->create(['schema' => ['fields' => $this->tableSchema]]);

        try {
            // Insert data into temporary table
            $table = $this->dataset->table($tempTableName);
            retry(3, static function () use ($table, $data) {
                $res = $table->insertRows($data);
                if (!$res->isSuccessful()){
                    throw new RuntimeException(json_encode($res->failedRows(), JSON_THROW_ON_ERROR));
                }
            }, 10000);

            // Determine unique key based on level
            $level = $this->row->settings['level'] ?? 'ad';
            $uniqueKeys = ['date', 'account_id'];
            
            if (in_array($level, ['campaign', 'adset', 'ad'], true)) {
                $uniqueKeys[] = 'campaign_id';
            }
            if (in_array($level, ['adset', 'ad'], true)) {
                $uniqueKeys[] = 'adset_id';
            }
            if ($level === 'ad') {
                $uniqueKeys[] = 'ad_id';
            }
            
            // Add breakdown fields if present
            $breakdowns = $this->row->settings['breakdowns'] ?? [];
            foreach (['country', 'platform_position', 'publisher_platform', 'age', 'region', 'gender'] as $breakdown) {
                if (in_array($breakdown, $breakdowns, true)) {
                    $uniqueKeys[] = $breakdown;
                }
            }

            // Build MERGE statement with NULL handling
            // In SQL, NULL = NULL returns NULL (not TRUE), so we need to handle NULLs properly
            $onConditions = [];
            foreach ($uniqueKeys as $key) {
                // Handle NULL values: use COALESCE to treat NULL as empty string for comparison
                // This ensures NULL values match correctly
                $onConditions[] = "COALESCE(CAST(target.{$key} AS STRING), '') = COALESCE(CAST(source.{$key} AS STRING), '')";
            }
            $onClause = implode(' AND ', $onConditions);

            // Get all columns for UPDATE
            $allColumns = array_column($this->tableSchema, 'name');
            $updateColumns = [];
            $insertColumns = [];
            $insertValues = [];
            
            foreach ($allColumns as $col) {
                if (!in_array($col, $uniqueKeys, true)) {
                    $updateColumns[] = "target.{$col} = source.{$col}";
                }
                $insertColumns[] = $col;
                $insertValues[] = "source.{$col}";
            }

            $updateClause = implode(', ', $updateColumns);
            $insertColsClause = implode(', ', $insertColumns);
            $insertValsClause = implode(', ', $insertValues);

            $mergeSql = "
                MERGE `{$fullTableName}` AS target
                USING `" . AdTable::DATASET . ".{$tempTableName}` AS source
                ON {$onClause}
                WHEN MATCHED THEN
                    UPDATE SET {$updateClause}
                WHEN NOT MATCHED THEN
                    INSERT ({$insertColsClause}) VALUES ({$insertValsClause})
            ";

            // Execute MERGE using BigQuery connection
            $connection = BigQuery::connection();
            $connection->runQuery($mergeSql);

            // Drop temporary table
            $tempTable->delete();

            $this->log('Saved to string table (MERGE): '.$count);
        } catch (Throwable $e) {
            // Clean up temp table on error
            try {
                $tempTable->delete();
            } catch (Throwable $cleanupError) {
                // Ignore cleanup errors
            }
            throw $e;
        }
    }

    protected function getFbData($act): array
    {
        $this->log("Getting FB data for account: {$act}, period: {$this->dayStart} to {$this->dayEnd}");
        try {
            $data = FbInsight::getInsightsAsync($act, $this->dayStart, $this->dayEnd, self::FIELDS_INSIGHTS, $this->row->settings['level'] ?? 'ad', $this->row->settings, true);
            $this->log("getInsightsAsync returned ".count($data)." records for account: {$act}");
        } catch (Throwable $e) {
            $this->log("Error in getInsightsAsync for account {$act}: ".$e->getMessage());
            throw $e;
        }

        if (!empty($this->row->settings['level']) && $this->row->settings['level'] === 'ad'){
            $ad_ids = array_unique(array_column($data, 'ad_id'));
            foreach ($ad_ids as $ad_id) {
                if (empty($this->adCreativesData[$ad_id])) {
                    $this->adCreativesData[$ad_id] = FbInsight::getAdCreatives($ad_id, self::FIELDS_AD_CREATIVES)[0] ?? [];
                }
            }
        }

        foreach ($data as $k => $datum) {
            if (!empty($datum['country'])) {
                $data[$k]['country'] = self::COUNTRIES[$datum['country']] ?? $datum['country'];
            }
            if (!empty($datum['ad_id']) && !empty($this->adCreativesData[$datum['ad_id']])) {
                // Thumbnail url
                if (!empty($this->adCreativesData[$datum['ad_id']]['thumbnail_url'])) {
                    $data[$k]['thumbnail_url'] = $this->adCreativesData[$datum['ad_id']]['thumbnail_url'];
                }

                // UTM
                if (!empty($this->adCreativesData[$datum['ad_id']]['url_tags'])) {
                    $str = $this->adCreativesData[$datum['ad_id']]['url_tags'];
                    $replaceStr = [
                        '{{campaign.name}}' => $datum['campaign_name'],
                        '{{adset.name}}' => $datum['adset_name'],
                        '{{ad.name}}' => $datum['ad_name'],
                        '{{campaign.id}}' => $datum['campaign_id'],
                        '{{adset.id}}' => $datum['adset_id'],
                        '{{ad.id}}' => $datum['ad_id']
                    ];
                    $str = str_replace(array_keys($replaceStr), array_values($replaceStr), $str);
                    parse_str($str, $query);
                    foreach (['utm_source', 'utm_term', 'utm_campaign', 'utm_content', 'utm_medium'] as $utm) {
                        if (!empty($query[$utm])) {
                            $data[$k][$utm] = $query[$utm];
                        }
                    }
                }
            }
        }
        return $data;
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

    public const array FIELDS_AD_CREATIVES = ['thumbnail_url', 'url_tags'];

    public const array FIELDS_INSIGHTS = [
        'account_id',
        'account_name',
        'campaign_name',
        'campaign_id',
        'adset_name',
        'adset_id',
        'ad_name',
        'ad_id',
        'account_currency',
        'actions',
        'action_values',
        'conversion_values',
        'conversions',
        'cost_per_action_type',
        'cost_per_conversion',
        'cost_per_unique_click',
        'cost_per_unique_action_type',
        'cost_per_outbound_click',
        'cost_per_unique_outbound_click',
        'cpc',
        'cpm',
        'cpp',
        'ctr',
        'frequency',
        'impressions',
        'purchase_roas',
        'reach',
        'spend'
    ];

    public const array DEFAULT_SCHEMA = [
        [
            "name" => "date",
            "type" => "DATE",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "account_id",
            "type" => "INTEGER",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "account_name",
            "type" => "STRING",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "campaign_name",
            "type" => "STRING",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "platform_position",
            "type" => "STRING",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "publisher_platform",
            "type" => "STRING",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "campaign_id",
            "type" => "INTEGER",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "ad_name",
            "type" => "STRING",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "ad_id",
            "type" => "INTEGER",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "adset_name",
            "type" => "STRING",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "adset_id",
            "type" => "INTEGER",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "account_currency",
            "type" => "STRING",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "actions",
            "type" => "RECORD",
            "mode" => "NULLABLE",
            "fields" => [
                [
                    "name" => "link_click",
                    "type" => "INTEGER",
                    "mode" => "NULLABLE"
                ]
            ]
        ],
        [
            "name" => "action_values",
            "type" => "RECORD",
            "mode" => "NULLABLE",
            "fields" => [
                [
                    "name" => "link_click",
                    "type" => "FLOAT",
                    "mode" => "NULLABLE"
                ]
            ]
        ],
        [
            "name" => "clicks",
            "type" => "INTEGER",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "country",
            "type" => "STRING",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "age",
            "type" => "STRING",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "region",
            "type" => "STRING",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "gender",
            "type" => "STRING",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "cost_per_unique_action_type",
            "type" => "RECORD",
            "mode" => "NULLABLE",
            "fields" => [
                [
                    "name" => "link_click",
                    "type" => "FLOAT",
                    "mode" => "NULLABLE"
                ]
            ]
        ],
        [
            "name" => "conversion_values",
            "type" => "RECORD",
            "mode" => "NULLABLE",
            "fields" => [
                [
                    "name" => "subscribe_total",
                    "type" => "FLOAT",
                    "mode" => "NULLABLE"
                ]
            ]
        ],
        [
            "name" => "conversions",
            "type" => "RECORD",
            "mode" => "NULLABLE",
            "fields" => [
                [
                    "name" => "subscribe_total",
                    "type" => "INTEGER",
                    "mode" => "NULLABLE"
                ]
            ]
        ],
        [
            "name" => "cost_per_action_type",
            "type" => "RECORD",
            "mode" => "NULLABLE",
            "fields" => [
                [
                    "name" => "link_click",
                    "type" => "FLOAT",
                    "mode" => "NULLABLE"
                ]
            ]
        ],
        [
            "name" => "cost_per_conversion",
            "type" => "RECORD",
            "mode" => "NULLABLE",
            "fields" => [
                [
                    "name" => "subscribe_total",
                    "type" => "FLOAT",
                    "mode" => "NULLABLE"
                ]
            ]
        ],
        [
            "name" => "cost_per_outbound_click",
            "type" => "RECORD",
            "mode" => "NULLABLE",
            "fields" => [
                [
                    "name" => "outbound_click",
                    "type" => "FLOAT",
                    "mode" => "NULLABLE"
                ]
            ]
        ],
        [
            "name" => "cost_per_unique_click",
            "type" => "FLOAT",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "cost_per_unique_outbound_click",
            "type" => "RECORD",
            "mode" => "NULLABLE",
            "fields" => [
                [
                    "name" => "outbound_click",
                    "type" => "FLOAT",
                    "mode" => "NULLABLE"
                ]
            ]
        ],
        [
            "name" => "purchase_roas",
            "type" => "RECORD",
            "mode" => "NULLABLE",
            "fields" => [
                [
                    "name" => "omni_purchase",
                    "type" => "FLOAT",
                    "mode" => "NULLABLE"
                ]
            ]
        ],
        [
            "name" => "reach",
            "type" => "INTEGER",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "frequency",
            "type" => "FLOAT",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "impressions",
            "type" => "INTEGER",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "spend",
            "type" => "FLOAT",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "cpc",
            "type" => "FLOAT",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "cpm",
            "type" => "FLOAT",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "cpp",
            "type" => "FLOAT",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "ctr",
            "type" => "FLOAT",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "thumbnail_url",
            "type" => "STRING",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "utm_source",
            "type" => "STRING",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "utm_medium",
            "type" => "STRING",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "utm_campaign",
            "type" => "STRING",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "utm_content",
            "type" => "STRING",
            "mode" => "NULLABLE"
        ],
        [
            "name" => "utm_term",
            "type" => "STRING",
            "mode" => "NULLABLE"
        ]
    ];

    public const array COUNTRIES = array(
        "AF" => "Afghanistan",
        "AL" => "Albania",
        "DZ" => "Algeria",
        "AS" => "American Samoa",
        "AD" => "Andorra",
        "AO" => "Angola",
        "AI" => "Anguilla",
        "AQ" => "Antarctica",
        "AG" => "Antigua and Barbuda",
        "AR" => "Argentina",
        "AM" => "Armenia",
        "AW" => "Aruba",
        "AU" => "Australia",
        "AT" => "Austria",
        "AZ" => "Azerbaijan",
        "BS" => "Bahamas",
        "BH" => "Bahrain",
        "BD" => "Bangladesh",
        "BB" => "Barbados",
        "BY" => "Belarus",
        "BE" => "Belgium",
        "BZ" => "Belize",
        "BJ" => "Benin",
        "BM" => "Bermuda",
        "BT" => "Bhutan",
        "BO" => "Bolivia (Plurinational State of)",
        "BQ" => "Bonaire, Sint Eustatius and Saba",
        "BA" => "Bosnia and Herzegovina",
        "BW" => "Botswana",
        "BV" => "Bouvet Island",
        "BR" => "Brazil",
        "IO" => "British Indian Ocean Territory",
        "BN" => "Brunei Darussalam",
        "BG" => "Bulgaria",
        "BF" => "Burkina Faso",
        "BI" => "Burundi",
        "CV" => "Cabo Verde",
        "KH" => "Cambodia",
        "CM" => "Cameroon",
        "CA" => "Canada",
        "KY" => "Cayman Islands",
        "CF" => "Central African Republic",
        "TD" => "Chad",
        "CL" => "Chile",
        "CN" => "China",
        "CX" => "Christmas Island",
        "CC" => "Cocos (Keeling) Islands",
        "CO" => "Colombia",
        "KM" => "Comoros",
        "CG" => "Congo",
        "CD" => "Congo, Democratic Republic of the",
        "CK" => "Cook Islands",
        "CR" => "Costa Rica",
        "HR" => "Croatia",
        "CU" => "Cuba",
        "CW" => "Curaçao",
        "CY" => "Cyprus",
        "CZ" => "Czechia",
        "DK" => "Denmark",
        "DJ" => "Djibouti",
        "DM" => "Dominica",
        "DO" => "Dominican Republic",
        "EC" => "Ecuador",
        "EG" => "Egypt",
        "SV" => "El Salvador",
        "GQ" => "Equatorial Guinea",
        "ER" => "Eritrea",
        "EE" => "Estonia",
        "SZ" => "Eswatini",
        "ET" => "Ethiopia",
        "FK" => "Falkland Islands (Malvinas)",
        "FO" => "Faroe Islands",
        "FJ" => "Fiji",
        "FI" => "Finland",
        "FR" => "France",
        "GF" => "French Guiana",
        "PF" => "French Polynesia",
        "TF" => "French Southern Territories",
        "GA" => "Gabon",
        "GM" => "Gambia",
        "GE" => "Georgia",
        "DE" => "Germany",
        "GH" => "Ghana",
        "GI" => "Gibraltar",
        "GR" => "Greece",
        "GL" => "Greenland",
        "GD" => "Grenada",
        "GP" => "Guadeloupe",
        "GU" => "Guam",
        "GT" => "Guatemala",
        "GG" => "Guernsey",
        "GN" => "Guinea",
        "GW" => "Guinea-Bissau",
        "GY" => "Guyana",
        "HT" => "Haiti",
        "HM" => "Heard Island and McDonald Islands",
        "VA" => "Holy See",
        "HN" => "Honduras",
        "HK" => "Hong Kong",
        "HU" => "Hungary",
        "IS" => "Iceland",
        "IN" => "India",
        "ID" => "Indonesia",
        "IR" => "Iran (Islamic Republic of)",
        "IQ" => "Iraq",
        "IE" => "Ireland",
        "IM" => "Isle of Man",
        "IL" => "Israel",
        "IT" => "Italy",
        "JM" => "Jamaica",
        "JP" => "Japan",
        "JE" => "Jersey",
        "JO" => "Jordan",
        "KZ" => "Kazakhstan",
        "KE" => "Kenya",
        "KI" => "Kiribati",
        "KP" => "Korea (Democratic People's Republic of)",
        "KR" => "Korea, Republic of",
        "KW" => "Kuwait",
        "KG" => "Kyrgyzstan",
        "LA" => "Lao People's Democratic Republic",
        "LV" => "Latvia",
        "LB" => "Lebanon",
        "LS" => "Lesotho",
        "LR" => "Liberia",
        "LY" => "Libya",
        "LI" => "Liechtenstein",
        "LT" => "Lithuania",
        "LU" => "Luxembourg",
        "MO" => "Macao",
        "MG" => "Madagascar",
        "MW" => "Malawi",
        "MY" => "Malaysia",
        "MV" => "Maldives",
        "ML" => "Mali",
        "MT" => "Malta",
        "MH" => "Marshall Islands",
        "MQ" => "Martinique",
        "MR" => "Mauritania",
        "MU" => "Mauritius",
        "YT" => "Mayotte",
        "MX" => "Mexico",
        "FM" => "Micronesia (Federated States of)",
        "MD" => "Moldova (Republic of)",
        "MC" => "Monaco",
        "MN" => "Mongolia",
        "ME" => "Montenegro",
        "MS" => "Montserrat",
        "MA" => "Morocco",
        "MZ" => "Mozambique",
        "MM" => "Myanmar",
        "NA" => "Namibia",
        "NR" => "Nauru",
        "NP" => "Nepal",
        "NL" => "Netherlands",
        "NC" => "New Caledonia",
        "NZ" => "New Zealand",
        "NI" => "Nicaragua",
        "NE" => "Niger",
        "NG" => "Nigeria",
        "NU" => "Niue",
        "NF" => "Norfolk Island",
        "MK" => "North Macedonia",
        "MP" => "Northern Mariana Islands",
        "NO" => "Norway",
        "OM" => "Oman",
        "PK" => "Pakistan",
        "PW" => "Palau",
        "PS" => "Palestine, State of",
        "PA" => "Panama",
        "PG" => "Papua New Guinea",
        "PY" => "Paraguay",
        "PE" => "Peru",
        "PH" => "Philippines",
        "PN" => "Pitcairn",
        "PL" => "Poland",
        "PT" => "Portugal",
        "PR" => "Puerto Rico",
        "QA" => "Qatar",
        "RE" => "Réunion",
        "RO" => "Romania",
        "RU" => "Russian Federation",
        "RW" => "Rwanda",
        "BL" => "Saint Barthélemy",
        "SH" => "Saint Helena, Ascension and Tristan da Cunha",
        "KN" => "Saint Kitts and Nevis",
        "LC" => "Saint Lucia",
        "MF" => "Saint Martin (French part)",
        "PM" => "Saint Pierre and Miquelon",
        "VC" => "Saint Vincent and the Grenadines",
        "WS" => "Samoa",
        "SM" => "San Marino",
        "ST" => "Sao Tome and Principe",
        "SA" => "Saudi Arabia",
        "SN" => "Senegal",
        "RS" => "Serbia",
        "SC" => "Seychelles",
        "SL" => "Sierra Leone",
        "SG" => "Singapore",
        "SX" => "Sint Maarten (Dutch part)",
        "SK" => "Slovakia",
        "SI" => "Slovenia",
        "SB" => "Solomon Islands",
        "SO" => "Somalia",
        "ZA" => "South Africa",
        "GS" => "South Georgia and the South Sandwich Islands",
        "SS" => "South Sudan",
        "ES" => "Spain",
        "LK" => "Sri Lanka",
        "SD" => "Sudan",
        "SR" => "Suriname",
        "SJ" => "Svalbard and Jan Mayen",
        "SE" => "Sweden",
        "CH" => "Switzerland",
        "SY" => "Syrian Arab Republic",
        "TW" => "Taiwan, Province of China",
        "TJ" => "Tajikistan",
        "TZ" => "Tanzania, United Republic of",
        "TH" => "Thailand",
        "TL" => "Timor-Leste",
        "TG" => "Togo",
        "TK" => "Tokelau",
        "TO" => "Tonga",
        "TT" => "Trinidad and Tobago",
        "TN" => "Tunisia",
        "TR" => "Turkey",
        "TM" => "Turkmenistan",
        "TC" => "Turks and Caicos Islands",
        "TV" => "Tuvalu",
        "UG" => "Uganda",
        "UA" => "Ukraine",
        "AE" => "United Arab Emirates",
        "GB" => "United Kingdom of Great Britain and Northern Ireland",
        "US" => "United States of America",
        "UM" => "United States Minor Outlying Islands",
        "UY" => "Uruguay",
        "UZ" => "Uzbekistan",
        "VU" => "Vanuatu",
        "VE" => "Venezuela (Bolivarian Republic of)",
        "VN" => "Viet Nam",
        "VG" => "Virgin Islands (British)",
        "VI" => "Virgin Islands (U.S.)",
        "WF" => "Wallis and Futuna",
        "EH" => "Western Sahara",
        "YE" => "Yemen",
        "ZM" => "Zambia",
        "ZW" => "Zimbabwe"
    );
}
