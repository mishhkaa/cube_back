<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdSourcesEvent extends Model
{

    protected $fillable = ['ad_source_id', 'day_start', 'day_stop', 'message'];

    public const UPDATED_AT = null;

    protected $casts = [
        'day_start' => 'date:Y-m-d',
        'day_stop' => 'date:Y-m-d',
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(AdSource::class, 'ad_source_id');
    }

    public function prunable(): Builder|AdSourcesEvent
    {
        return static::query()->where('created_at', '<', Carbon::now()->subWeek());
    }
}
