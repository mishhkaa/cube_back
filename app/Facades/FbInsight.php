<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;
/**
 * @see \App\Classes\ApiClients\FbInsightClient
 */
class FbInsight extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'fbinsight';
    }

}
