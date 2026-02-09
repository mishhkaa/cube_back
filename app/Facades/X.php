<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \App\Classes\ApiClients\XClient
 */
class X extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'x-api';
    }
}
