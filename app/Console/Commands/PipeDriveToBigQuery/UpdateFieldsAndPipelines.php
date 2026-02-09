<?php

namespace App\Console\Commands\PipeDriveToBigQuery;

use App\Facades\PipeDrive;
use Google\Cloud\BigQuery\Dataset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pelfox\LaravelBigQuery\Facades\BigQuery;

class UpdateFieldsAndPipelines extends Command
{
    protected $signature = 'bigquery:pipedrive:types {--types=}';

    protected $description = '';

    private const string DATASET = 'pipedrive';

    protected array $tablesWithFields = [
        'dealFields' => ['id', 'key', 'name', 'order_nr', 'field_type', 'options'],
        'personFields' => ['id', 'key', 'name', 'order_nr', 'field_type', 'options'],
        'organizationFields' => ['id', 'key', 'name', 'order_nr', 'field_type', 'options'],
        'pipelines' => ['id', 'name', 'order_nr', 'active'],
        'stages' => ['id', 'order_nr', 'name', 'pipeline_id']
    ];

    protected Dataset $dataset;

    public function handle(): void
    {
        $this->dataset = BigQuery::dataset(self::DATASET);

        $types = array_keys($this->tablesWithFields);

        if ($typesOption = $this->option('types')) {
            $types = explode(',', $typesOption);
        }

        foreach ($types as $item) {
            if (!isset($this->tablesWithFields[$item])) {
                continue;
            }

            $data = $this->getDataFromPipeDrive($item);
            if (!$data) {
                Log::info('Pipedrive to BigQuery: not ' . $item . ' data');
                continue;
            }

            $data = $this->formatterData($item, $data);
            $this->updateDataInBigQuery($item, $data);
        }
    }

    protected function formatterData($type, $data): array
    {
        $fields = $this->tablesWithFields[$type];

        $arr = [];
        foreach ($data as $values) {
            foreach ($values as $field => $value) {
                if (!in_array($field, $fields)) {
                    unset($values[$field]);
                    continue;
                }
                if ($field == 'options') {
                    $values[$field] = array_map(function ($v) {
                        return [
                            'id' => $v['id'] ?? '',
                            'label' => $v['label'] ?? '',
                        ];
                    }, $value);
                }
            }
            $arr[] = ['data' => $values];
        }
        return $arr;
    }

    protected function getDataFromPipeDrive($url): array
    {
        return PipeDrive::sendRequest('/' . $url)->toArray();
    }

    protected function updateDataInBigQuery($table, $data): void
    {
        $this->checkAndCrateTable($table);

        $this->deleteDataFromBigQuery($table);

        $this->dataset->table($table)->insertRows($data);
    }

    protected function deleteDataFromBigQuery($table): void
    {
        DB::connection('bigquery')->table(self::DATASET . ".$table")
            ->truncate();
    }


    protected function checkAndCrateTable($table): void
    {
        if ($this->dataset->table($table)->exists()) {
            return;
        }

        $schema = $this->getSchemaTable($table);

        $this->dataset->createTable($table, ['schema' => ['fields' => $schema]]);
        sleep(10);
    }

    protected function getSchemaTable($table): array
    {
        $schema = [];
        foreach ($this->tablesWithFields[$table] as $key) {
            $field = [
                'name' => $key,
                'type' => 'STRING',
                'mode' => 'NULLABLE'
            ];

            if (in_array($key, ['id', 'order_nr', 'pipeline_id'])) {
                $field['type'] = 'INTEGER';
            } elseif ($key == 'active') {
                $field['type'] = 'BOOLEAN';
            } elseif ($key == 'options') {
                $field['type'] = 'RECORD';
                $field['mode'] = 'REPEATED';
                $field['fields'] = [
                    [
                        'name' => 'id',
                        'type' => 'STRING',
                        'mode' => 'NULLABLE'
                    ],
                    [
                        'name' => 'label',
                        'type' => 'STRING',
                        'mode' => 'NULLABLE'
                    ],
                ];
            }

            $schema[] = $field;
        }

        return $schema;
    }
}
