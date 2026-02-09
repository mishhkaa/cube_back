<?php

namespace App\Notifications;

use App\Classes\SlackNotification\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FbAdAccountsBalance extends Notification
{
    use Queueable;

    public function __construct(protected array $data, protected string $project)
    {
    }

    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): Message
    {
        $text[] = '*Проект: ' . $this->project . '*';
        $text[] = '';

        foreach ($this->data as $account) {
            $text[] = 'Кабінет: *' . $account['name'] . '* - ' . str_replace('act_', '', $account['id']);
            $text[] = 'Баланс: *' . $account['amount_residual'] . $account['currency'] . '*';
            $text[] = 'Щоденний розхід: *' . $account['spend_daily'] . $account['currency'] . '*';
            $text[] = 'До закінчення балансу: *' . $account['residual_days'] . 'д.*';
            $text[] = 'Тижневий розхід: *' . round($account['spend_daily'] * 7, 2) . $account['currency']  . '*';
            $text[] = '';
        }

        return (new Message())
            ->text(implode(PHP_EOL, $text));
    }
}
