<?php

namespace App\Models;

use App\Models\Traits\Active;
use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    public const SERVICE_UPWORK_SLACK = 'upwork-slack';

    public const SERVICE_FB_ADS_BALANCES = 'fb-ads-balances';

    public const DASHBOARD = 'dashboard';

    use Active;

    protected $fillable = ['name', 'type', 'object_id', 'config', 'active'];

    protected $casts = [
        'object_id' => 'integer',
        'config' => 'array',
        'active' => 'boolean',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s'
    ];
}
