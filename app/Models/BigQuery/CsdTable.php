<?php

namespace App\Models\BigQuery;

use App\Models\CsdProject;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CsdTable extends Model
{
    protected $connection = 'bigquery';

    protected $table = 'csd.all';

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = ['name', 'date', 'platform', 'ad_account', 'ad_account_id', 'spend'];
}
