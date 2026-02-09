<?php

namespace App\Console\Commands\PipeDriveToBigQuery;

use App\Facades\PipeDrive;
use App\Models\BigQuery\PipeDrive\Deal;
use App\Models\Request;
use Carbon\CarbonPeriod;
use Google\Cloud\BigQuery\Table;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Pelfox\LaravelBigQuery\Facades\BigQuery;

class Deals extends Command
{
    protected $signature = 'pipedrive-to-bigquery:deals {--all} {--date=}';

    protected array $dealsFields = [
        'id',
        'title',
        'value',
        'active',
        'org_id',
        'status',
        'deleted',
        'user_id',
        'add_time',
        'currency',
        'stage_id',
        'won_time',
        'lost_time',
        'person_id',
        'close_time',
        'pipeline_id',
        'update_time',
        'creator_user_id',
        'lost_reason'
    ];

    protected Table $table;

    public function handle(): void
    {
        if (!$data = $this->getData()) {
            return;
        }

        $this->table = Deal::getAsBigQueryTableInstance();

        $this->updateSchemaTable($data[0]);

        $data = $this->formatterData($data);
        $this->insertData($data);
    }

    protected function getCorrectValue(string $key, array $item)
    {
        return match ($key) {
            'active' => $item['status'] === 'open',
            'deleted' => $item['status'] === 'deleted',
            'user_id' => $item['owner_id'],
            default => $item[$key]
        };
    }


    protected function updateSchemaTable($dael): void
    {
        if (!$this->table->exists()) {
            $this->table = BigQuery::dataset(Deal::DATASET)->createTable(Deal::TABLE);
            for ($i = 0; $i < 10; $i++) {
                if ($this->table->exists()) {
                    break;
                }
                sleep(10);
            }
        }

        if ($updatedFields = $this->getUpdatedFields($dael)) {
            $this->table->update(['schema' => ['fields' => $updatedFields]]);
            sleep(10);
        }
    }


    protected function getUpdatedFields($dael): array
    {
        $fieldsSchema = $this->table->info()['schema']['fields'] ?? [];

        $columns = array_column($fieldsSchema, 'name');

        $isNewFields = false;

        foreach ($this->dealsFields as $field) {
            if (!in_array($field, $columns, true)) {
                $isNewFields = true;
                $arr = [
                    'name' => $field,
                    'mode' => 'NULLABLE',
                ];

                $arr['type'] = match (true){
                    $field === 'id' || stripos($field, '_id') !== false => 'INTEGER',
                    $field === 'value' => 'FLOAT',
                    stripos($field, '_time') !== false => 'DATETIME',
                    in_array($field, ['active', 'deleted']) => 'BOOLEAN',
                    default => 'STRING',
                };

                $fieldsSchema[] = $arr;
            }
        }

        $customFieldsSchema = [];
        $customFieldsSchemaColumns = [];
        foreach ($fieldsSchema as $key => $item) {
            if ($item['name'] === 'custom_fields') {
                $customFieldsSchema = $item;
                $customFieldsSchemaColumns = array_column($item['fields'] ?? [], 'name');
                unset($fieldsSchema[$key]);
                break;
            }
        }
        if (!$customFieldsSchema) {
            $customFieldsSchema = [
                'name' => 'custom_fields',
                'type' => 'RECORD',
                'mode' => 'NULLABLE',
                'fields' => []
            ];
        }

        if (!empty($dael['custom_fields'])) {
            foreach (array_keys($dael['custom_fields']) as $key) {
                if (!in_array($key, $customFieldsSchemaColumns, true)) {
                    $isNewFields = true;
                    $customFieldsSchema['fields'][] = [
                        'name' => $key,
                        'mode' => 'NULLABLE',
                        'type' => 'STRING'
                    ];
                }
            }
        }
        return $isNewFields ? array_values(array_merge($fieldsSchema, [$customFieldsSchema])) : [];
    }


    protected function getData(): array
    {
        if ($this->option('all')) {
            return PipeDrive::sendRequest('/deals')->toArray();
        }

        $rows = Request::query()
            ->where('action', 'pipedrive-bigquery-add')
            ->when($this->option('date'), function (Builder $builder, $date) {
                $period = CarbonPeriod::between(...explode('...', $date));
                $builder->where('created_at', '>=', $period->getStartDate()->format('Y-m-d 00:00:00'));
                $builder->where('created_at', '<=', $period->getEndDate()->format('Y-m-d 23:59:59'));
            }, function (Builder $builder) {
                $dateTime = Carbon::now()->subHour();
                $builder->where('created_at', '>=', $dateTime->format('Y-m-d H:00:00'));
                $builder->where('created_at', '<=', $dateTime->format('Y-m-d H:59:59'));
            })
            ->get(['post', 'message']);

        $data = [];

        foreach ($rows as $row) {
            if (empty($row->post['data'])) {
                continue;
            }
            $data[] = $item = $row->post['data'];
            $item += $item['custom_fields'] ?? [];
            $ids = explode(',', $row->message ?: '');
            foreach ($ids as $id) {
                if (is_numeric($id)) {
                    $item['stage_id'] = $id;
                    $data[] = $item;
                }
            }
        }

        return $data;
    }

    protected function formatterData($data): array
    {
        $arr = [];

        foreach ($data as $item) {
            foreach ($item as $key => $value) {
                if (is_null($value)) {
                    unset($item[$key]);
                    continue;
                }
                if (in_array($key, $this->dealsFields, true) || isset($this->dealsFields[$key])) {
                    $value = $this->getCorrectValue($key, $item);
                    if (stripos($key, '_id') !== false) {
                        $item[$key] = $value['value'] ?? $value;
                    }
                    if (in_array($key, ['add_time', 'update_time', 'close_time', 'lost_time', 'won_time'])){
                        $item[$key] = Carbon::parse($value)->format('Y-m-d H:i:s');
                    }
                } elseif ($key === 'custom_fields') {
                    foreach ($value as $index => $v) {
                        $item['custom_fields'][$index] = $this->getCustomFieldValue($v);
                    }
                    unset($item['custom_fields']);
                } else {
                    unset($item[$key]);
                }
            }

            $arr[] = ['data' => $item];
        }
        return $arr;
    }

    protected function getCustomFieldValue(mixed $valueObject): mixed
    {
        if (empty($valueObject)) {
            return null;
        }

        return match ($valueObject['type']){
            'monetary' => $valueObject['value'] . $valueObject['currency'],
            'timerange', 'daterange' => $valueObject['from'] . '-' . $valueObject['until'],
            'set' => implode(',', array_column($valueObject['values'], 'id')),
            default => $field['value'] ?? ($valueObject['id'] ?? null)
        };
    }

    protected function insertData($data): void
    {
        if ($this->option('all')) {
            Deal::query()->truncate();
        }

        if ($date = $this->option('date')) {
            $period = CarbonPeriod::between(...explode('...', $date));
            Deal::query()
                ->where('update_time', '>=', $period->getStartDate()->format('Y-m-d 00:00:00'))
                ->where('update_time', '<=', $period->getEndDate()->format('Y-m-d 23:59:59'))
                ->delete();
        }

        $res = $this->table->insertRows($data);
        if (!$res->isSuccessful()) {
            foreach ($res->failedRows() as $row) {
                Log::info('DealsBigQuery: ', $row);
            }
        }
    }
}
