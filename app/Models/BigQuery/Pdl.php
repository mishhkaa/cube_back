<?php

namespace App\Models\BigQuery;

use Illuminate\Database\Eloquent\Model;

class Pdl extends Model
{
    protected $connection = 'bigquery';

    public const DATASET = 'data_studio';
    public const TABLE = 'pdl';
    protected $table = self::DATASET. '.' . self::TABLE;

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;
}
