<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \App\Classes\ApiClients\MailchimpClient
 */
class Mailchimp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mailchimp';
    }
}
