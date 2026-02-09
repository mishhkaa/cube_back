<?php

namespace App\Models\BigQuery\PipeDrive;

use App\Models\Traits\BigQueryModelHelpers;
use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    use BigQueryModelHelpers;

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $connection = 'bigquery';

    public const DATASET = 'pipedrive';
    public const TABLE = 'deals';

    protected $table = self::DATASET. '.' . self::TABLE;
}
