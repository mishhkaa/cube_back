<?php

namespace App\Classes\Macroable;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class BuilderMixin
{
    public function getRaw(): Closure
    {
        return function ($columns = ['*']) {
            /** @var Builder $this */
            $this->applyScopes();
            return $this->query->get($columns)->all();
        };
    }
}
