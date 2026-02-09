<?php

namespace App\Notifications;

use App\Classes\SlackNotification\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;

class UpWorkJob extends Notification
{
    use Queueable;

    private const string ACTION_NAME = 'upwork_to_work';

    public function __construct(
        protected string $message,
        protected ?string $action = null,
        protected ?string $slackUser = null,
        protected ?string $slackTs = null
    )
    {
    }

    public function via(object $notifiable): array
    {
        return ['slack'];
    }


    public function toSlack(object $notifiable): Message
    {
        $message = (new Message())
            ->update($this->slackTs)
            ->sectionBlock(function (SectionBlock $block){
                $block->text($this->message)->markdown();
            });

        if ($this->slackUser){
            $message->sectionBlock(function (SectionBlock $block){
                $block->text("*Взяв в роботу: @{$this->slackUser}*")->markdown();
            });
        }

        match ($this->action){
            'approved', 'rejected' => $this->finishMessage($message, $this->action),
            'approve' => $this->approveAction($message),
            default => $this->defaultAction($message)
        };

        return $message;
    }

    protected function finishMessage(Message $message, string $status): void
    {
        $text = $status === 'approved' ? '✅ Прийнято' : '⛔ Відхилено';
        $message->sectionBlock(function (SectionBlock $block) use ($text) {
            $block->text("*Статус: $text*")->markdown();
        });
    }

    protected function approveAction(Message $message): void
    {
        $message->actionsBlock(function (ActionsBlock $block){
            $block->button('Прийняти')->primary()
                ->id(self::ACTION_NAME . ':approve');
            $block->button('Відхилити')->danger()
                ->id(self::ACTION_NAME . ':reject');
        });
    }

    protected function defaultAction(Message $message): void
    {
        $message->actionsBlock(function (ActionsBlock $block){
            $block->button('Взяти в роботу')
                ->primary()->id(self::ACTION_NAME . ':start');
        });
    }

}
