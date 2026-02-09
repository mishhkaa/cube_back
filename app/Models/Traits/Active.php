<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

trait Active
{

    public function scopeActive(Builder $query, bool $active = true): void
    {
        $query->where('active', $active);
    }

    public function disable(): static
    {
        return tap($this, static function (Model $model){
            $model->update(['active' => false]);
        });
    }
}
