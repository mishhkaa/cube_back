<?php

namespace App\Facades;

use App\Contracts\CurrencyInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @see CurrencyInterface
 */
class Currency extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'currency';
    }
}
