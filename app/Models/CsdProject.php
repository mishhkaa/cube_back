<?php

namespace App\Models;

use App\Models\BigQuery\CsdTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsdProject extends Model
{
    protected $fillable = ['name', 'ad_accounts', 'user_id'];

    protected function casts()
    {
        return [
            'ad_accounts' => 'array'
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
