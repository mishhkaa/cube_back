<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \App\Classes\ApiClients\PipeDriveClient
 */
class PipeDrive extends Facade
{

    protected static function getFacadeAccessor(): string
    {
        return 'pipedrive';
    }
}
