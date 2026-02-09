<?php

namespace App\Models\BigQuery;

use App\Models\AdSource;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class AdTable extends Model
{
    protected $connection = 'bigquery';

    public const string DATASET = 'data_studio';

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    /**
     * @throws Exception
     */
    public function getTable(): string
    {
        if (isset($this->table)) {
            return $this->table;
        }
        throw new RuntimeException('Set table name using static method "table" before query');
    }

    public static function table(AdSource $adSource): Builder
    {
        $table = $adSource->getBigQueryTableName();

        return (new static())->setTable(self::DATASET.".$table")->newQuery();
    }
}
