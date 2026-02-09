<?php

namespace App\Models;

use App\Contracts\ConversionAccountInterface;
use App\Models\Traits\Active;
use App\Models\Traits\ImplementConversionAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

abstract class Pixel extends Model implements ConversionAccountInterface
{
    use Active;
    use ImplementConversionAccount;

    protected $table = 'pixels';

    protected $fillable = ['user_id', 'name', 'source', 'currency',
        'pixel_id', 'access_token', 'active', 'testing'];

    protected $casts = [
        'active' => 'boolean',
        'testing' => 'boolean',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
