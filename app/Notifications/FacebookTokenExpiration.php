<?php

namespace App\Notifications;

use App\Classes\SlackNotification\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FacebookTokenExpiration extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['slack'];
    }


    public function toSlack(object $notifiable): Message
    {
        $text[] = 'До закінчення терміну дії доступу до рекламних кабінетів Facebook залишилось 5 днів.';
        $text[] = 'Перейдіть в адмін панель та оновіть доступ';
        $text[] = 'https://app.median-grp.com/users/' .  $notifiable->id;
        return (new Message())
            ->text(implode("\n", $text));
    }
}
