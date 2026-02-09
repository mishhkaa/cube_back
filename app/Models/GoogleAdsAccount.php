<?php

namespace App\Models;

use App\Contracts\ConversionAccountInterface;
use App\Models\Traits\ImplementConversionAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleAdsAccount extends Model implements ConversionAccountInterface
{
    use ImplementConversionAccount;

    protected $fillable = ['name', 'currency', 'active', 'user_id'];

    protected $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getSourceName(): string
    {
        return 'gads_offline_conversions';
    }
}
