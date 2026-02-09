<?php

namespace App\Models\Traits;

use Google\Cloud\BigQuery\Table;
use Pelfox\LaravelBigQuery\Facades\BigQuery;

trait BigQueryModelHelpers
{
    public static function getAsBigQueryTableInstance(): Table
    {
        $table = (new static())->getTable();
        $paths = explode('.', $table);
        $tableName = array_pop($paths);
        return BigQuery::dataset(array_pop($paths))->table($tableName);
    }
}
