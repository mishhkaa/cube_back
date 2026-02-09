<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \App\Classes\ApiClients\GoogleAdsClient
 */
class GoogleAds extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'google-ads';
    }
}
