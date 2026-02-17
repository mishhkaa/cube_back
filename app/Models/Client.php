<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name', 'link', 'unit', 'niche', 'description',
        // Project Card поля - Етап 1
        'project_id', 'ad_networks', 'status', 'start_date', 'end_date',
        'creative_sponsor', 'direction', 'sub_niche', 'scaling',
        'project_status', 'ad_account_identifiers',
        // Етап 2
        'team_lead_id', 'ppc_team_service',
    ];

    protected $casts = [
        'unit' => 'integer',
        'team_lead_id' => 'integer',
        'ad_networks' => 'array',
        'scaling' => 'array',
        'ad_account_identifiers' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_user');
    }

    public function adSources(): HasMany
    {
        return $this->hasMany(AdSource::class);
    }

    public function teamLead(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }
}
