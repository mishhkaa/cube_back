<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \App\Models\Request
 */

class RequestLog extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'request.log';
    }
}
