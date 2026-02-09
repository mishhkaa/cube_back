<?php

namespace App\Classes\SlackNotification;

use Illuminate\Http\Client\Response;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackChannel;
use LogicException;
use RuntimeException;

class Channel extends SlackChannel
{
    public function send(mixed $notifiable, Notification $notification): ?Response
    {
        $route = $this->determineRoute($notifiable, $notification);

        $message = $notification->toSlack($notifiable);

        $payload = $this->buildJsonPayload($message, $route);

        if (! $payload['channel']) {
            throw new LogicException('Slack notification channel is not set.');
        }

        if (! $route->token) {
            throw new LogicException('Slack API authentication token is not set.');
        }

        $uri = 'chat.postMessage';
        if (!empty($payload['ts'])){
            $uri = 'chat.update';
        }

        $response = $this->http->asJson()
            ->withToken($route->token)
            ->post('https://slack.com/api/' . $uri, $payload)
            ->throw();

        if ($response->successful() && $response->json('ok') === false) {
            throw new RuntimeException('Slack API call failed with error ['.$response->json('error').'].');
        }

        return $response;
    }
}
