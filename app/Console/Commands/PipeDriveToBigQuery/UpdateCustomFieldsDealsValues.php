<?php

namespace App\Console\Commands\PipeDriveToBigQuery;

use App\Facades\PipeDrive;
use App\Models\BigQuery\PipeDrive\Deal;
use Illuminate\Console\Command;
use RuntimeException;

class UpdateCustomFieldsDealsValues extends Command
{
    public $signature = 'pipedrive-bigquery:deals:update-custom-fields';

    public function handle(): void
    {
        if (!$deals = $this->getLastUpdatedDeals()){
            return;
        }

        $this->updateDataInBigQuery($this->formatted($deals));
    }

    protected function formatted($deals): array
    {
        $data = [];
        foreach ($deals as $deal) {
            $row = [];
            foreach (['value', 'active', 'status', 'deleted', 'currency', 'title'] as $key){
                if ($value = ($deal[$key] ?? null)){
                    $row[$key] = $value;
                }
            }
            foreach ($deal as $k => $v){
                if ($v && preg_match('/^[0-9a-z]{40}(_currency)?$/', $k)){
                    $row['custom_fields.' . $k] = (string)$v;
                }
            }
            if ($row){
                $data[$deal['id']] = $row;
            }
        }
        return $data;
    }

    protected function getLastUpdatedDeals(): array
    {
        return PipeDrive::sendRequest('/deals', ['filter_id' => '85'])->toArray();
    }

    protected function updateDataInBigQuery($data): void
    {
        $bigQueryTable = Deal::getAsBigQueryTableInstance();
        if (!$bigQueryTable->exists()){
            throw new RuntimeException("Table not exist");
        }
        foreach ($data as $id => $fields){
            Deal::query()->where('id', $id)
                ->update($fields);
        }
    }
}
