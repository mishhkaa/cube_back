<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \App\Classes\ApiClients\TikTokClient
 */
class TikTok extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'tiktok-api';
    }
}
