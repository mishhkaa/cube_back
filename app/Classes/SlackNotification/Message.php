<?php

namespace App\Classes\SlackNotification;

use Illuminate\Notifications\Slack\SlackMessage;

class Message extends SlackMessage
{
    protected ?string $ts = null;
    private ?string $threadTimestamp = null;

    public function update(?string $ts): static
    {
        $this->ts = $ts;

        return $this;
    }

    public function threadTimestamp(?string $threadTimestamp): static
    {
        $this->threadTimestamp = $threadTimestamp;

        return $this;
    }

    public function toArray(): array
    {
        $arr = parent::toArray();

        if ($this->ts) {
            $arr['ts'] = $this->ts;
            unset(
                $arr['icon_emoji'],
                $arr['icon_url'],
                $arr['mrkdwn'],
                $arr['thread_ts'],
                $arr['unfurl_links'],
                $arr['unfurl_media'],
                $arr['username'],
            );
        }
        if ($this->threadTimestamp){
            $arr['thread_ts'] = $this->threadTimestamp;
        }

        return $arr;
    }
}
