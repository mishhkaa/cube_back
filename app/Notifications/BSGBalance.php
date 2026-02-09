<?php

namespace App\Notifications;

use App\Classes\SlackNotification\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BSGBalance extends Notification
{
    use Queueable;

    public function __construct(protected int $value)
    {
    }

    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): Message
    {
        return (new Message())
            ->text("На балансі смс розсилок залишилося менше \${$this->value}. Поповніть рахунок https://app.bsg.world/auth");
    }
}
