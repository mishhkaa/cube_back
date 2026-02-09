<?php

namespace App\Classes\SlackNotification;

use Illuminate\Notifications\SlackNotificationRouterChannel;


class NotificationRouterChannel extends SlackNotificationRouterChannel
{
    protected function determineChannel($route)
    {
        return $this->app->make(Channel::class);
    }
}
