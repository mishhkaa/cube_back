<?php

namespace App\Console\Commands\PipeDriveToBigQuery;

use App\Facades\PipeDrive;
use Google\Cloud\BigQuery\Dataset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pelfox\LaravelBigQuery\Facades\BigQuery;

class UpdateDealsObjects extends Command
{
    protected $signature = 'bigquery:pipedrive:essences';

    protected $description = '';

    private const string DATASET = 'pipedrive';

    protected array $essences = [
        'persons' => [
            'filter_id' => 76,
            'fields' => ['id', 'owner_id', 'org_id', 'name', 'open_deals_count','closed_deals_count', 'lost_deals_count',
                'delete_time', 'phone', 'email', 'update_time', 'add_time']
        ],
        'organizations' => [
            'filter_id' => 77,
            'fields' => ['id', 'name', 'owner_id', 'open_deals_count', 'closed_deals_count', 'people_count', 'address']
        ],
        'users' => [
            'fields' => ['id', 'name', 'icon_url']
        ]
    ];

    protected Dataset $dataset;

    public function handle(): void
    {
        $this->dataset = BigQuery::dataset(self::DATASET);

        foreach (array_keys($this->essences) as $essence) {
            $data = $this->getData($essence);

            if (!$data){
                continue;
            }
            $this->updateSchemaTable($essence, array_keys($data[0]));

            $this->deleteData($essence, array_column($data, 'id'));

            $data = $this->formatterData($essence, $data);

            $this->insertData($essence, $data);
        }
    }

    protected function insertData($table, $data): void
    {
        $res = $this->dataset->table($table)->insertRows($data);
        if (!$res->isSuccessful()){
            foreach ($res->failedRows() as $row) {
                Log::info('UpdateDealsObjects: ', $row);
            }
        }
    }

    protected function updateSchemaTable($table, $fields): void
    {
        $tableBq = $this->dataset->table($table);
        if (!$tableBq->exists()){
            $tableBq = $this->dataset->createTable($table);
            for ($i = 0;$i < 10; $i++){
                if ($tableBq->exists()){
                    break;
                }
                sleep(10);
            }
        }

        if ($updatedFields = $this->getUpdateFields($table, $fields)){
            $tableBq->update(['schema' => ['fields' => $updatedFields]]);
            sleep(60);
        }
    }

    protected function getUpdateFields($table, $fields): array
    {
        $fieldsSchema = $this->dataset->table($table)->info()['schema']['fields'] ?? [];

        $columns = array_column($fieldsSchema, 'name');

        $isNewFields = false;

        foreach ($this->essences[$table]['fields'] as $field){
            if (!in_array($field, $columns, true)){
                $isNewFields = true;
                $arr = [
                    'name' => $field,
                    'mode' => 'NULLABLE',
                    'type' => 'STRING'
                ];
                if ($field === 'id' || stripos($field, '_id') !== false || stripos($field, '_count')){
                    $arr['type'] = 'INTEGER';
                }
                if (stripos($field, '_time') !== false){
                    $arr['type'] = 'DATETIME';
                }
                $fieldsSchema[] = $arr;
            }
        }

        $customFieldsSchema = [];
        $customFieldsSchemaColumns = [];
        foreach ($fieldsSchema as $key => $item){
            if ($item['name'] === 'custom_fields'){
                $customFieldsSchema = $item;
                $customFieldsSchemaColumns = array_column($item['fields'] ?? [], 'name');
                unset($fieldsSchema[$key]);
            }
        }
        if (!$customFieldsSchema){
            $customFieldsSchema = [
                'name' => 'custom_fields',
                'type' => 'RECORD',
                'mode' => 'NULLABLE',
                'fields' => []
            ];
        }

        foreach ($fields as $key){
            if (preg_match('/^[0-9a-z]{40}(_currency)?$/', $key) && !in_array($key, $customFieldsSchemaColumns, true)){
                $isNewFields = true;
                $customFieldsSchema['fields'][] = [
                    'name' => $key,
                    'mode' => 'NULLABLE',
                    'type' => 'STRING'
                ];
            }
        }
        return $isNewFields ? array_values(array_merge($fieldsSchema, [$customFieldsSchema])) : [];
    }

    protected function formatterData($essence, $data): array
    {
        $arr = [];

        $fields = $this->essences[$essence]['fields'];

        foreach ($data as $item) {
            foreach ($item as $key => $value) {
                if (is_null($value)){
                    unset($item[$key]);
                    continue;
                }
                if (in_array($key, $fields, true)){
                    if ($key === 'owner_id' && is_array($value)){
                        $item[$key] = $value['id'];
                    }
                    if ($key === 'org_id' && is_array($value)){
                        $item[$key] = $value['value'];
                    }
                    if (is_array($value) && in_array($key, ['phone', 'email'])){
                        $item[$key] = implode(',', array_column($value, 'value'));
                    }
                }elseif (preg_match('/^[0-9a-z]{40}(_currency)?$/', $key)){
                    $item['custom_fields'][$key] = (string)$value;
                    unset($item[$key]);
                }else{
                    unset($item[$key]);
                }
            }

            $arr[] = ['data' => $item];
        }

        return $arr;
    }

    protected function getData($essence): array
    {
        $url = '/' . $essence;
        if (!empty($this->essences[$essence]['filter_id'])){
            $url .= '?filter_id=' . $this->essences[$essence]['filter_id'];
        }

        return PipeDrive::sendRequest($url)->toArray();
    }

    protected function deleteData($table, $ids = []): void
    {
        if (!$ids){
            return;
        }

        DB::connection('bigquery')
            ->table(self::DATASET . ".$table")
            ->whereIn('id', $ids)
            ->delete();
    }
}
