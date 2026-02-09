<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Builder;

class TrackingUser extends Model
{
    use Prunable;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'data'];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ['integrations'];

    private const array INTEGRATIONS = [
        'fbp' => 'Facebook CApi',
        'ip' => 'TikTok Events',
        'gclid' => 'Google Ads Conversions',
    ];

    public function integrations(): Attribute
    {
        return Attribute::get(function () {
            if (empty($this->data)) {
                return [];
            }
            $integrations = [];
            foreach (self::INTEGRATIONS as $key => $value) {
                if (isset($this->data[$key])) {
                    $integrations[] = $value;
                }
            }
            return $integrations;
        });
    }

    public function prunable(): Builder|TrackingUser
    {
        return static::query()
            ->where('updated_at', '<', Carbon::now()->subWeeks(2))
            ->orWhere(function (Builder $query){
            $query->whereNull('updated_at')
                ->where('created_at', '<', Carbon::now()->subWeeks(2));
        });
    }
}
