<?php

namespace App\Models;

use App\Models\BigQuery\AdTable;
use Google\Cloud\BigQuery\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Pelfox\LaravelBigQuery\Facades\BigQuery;

class AdSource extends Model
{
    protected $fillable = ['name', 'platform', 'accounts', 'settings', 'currency', 'active', 'user_id'];

    protected $casts = [
        'accounts' => 'array',
        'settings' => 'array',
        'active' => 'boolean'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AdSourcesEvent::class);
    }

    public function getBigQueryTable(): Table
    {
        return BigQuery::dataset(AdTable::DATASET)->table($this->getBigQueryTableName());
    }

    public function getBigQueryTableName(): string
    {
        return $this->platform . '_ads_' . $this->name;
    }
}
