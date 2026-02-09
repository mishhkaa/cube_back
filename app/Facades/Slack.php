<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \App\Classes\ApiClients\SlackClient
 */
class Slack extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'slack';
    }
}
