<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleSheetAccount extends Model
{
    protected $fillable = ['name', 'spreadsheet_id', 'sheet_id', 'has_header', 'active', 'project_id', 'user_id'];

    protected $casts = [
        'active' => 'boolean',
        'has_header' => 'boolean',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
