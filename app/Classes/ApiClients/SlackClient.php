<?php

namespace App\Classes\ApiClients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SlackClient
{
    private PendingRequest $client;

    public function __construct()
    {
        if (!$token = config('services.slack.notifications.bot_user_oauth_token')){
            throw new RuntimeException("token no set in config");
        }

        $this->client = Http::withToken($token)
            ->baseUrl('https://slack.com/api/')
            ->asJson();
    }


    public function getUsers(): array|null
    {
        return $this->client->get('users.list')->json('members');
    }

    public function getUserByEmail(string $email): array|null
    {
        return $this->client->get('users.lookupByEmail', ['email' => $email])
            ->json('user');
    }
}
