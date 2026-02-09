<?php

namespace App\Console\Commands\Notifications;

use App\Facades\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

class BSGBalance extends Command
{
    protected $signature = 'notifications:bsg-balance';

    public function handle(): void
    {
        $info = $this->getBalanceInfo();

        $currency = $info['data']['currency'];
        $balance = $info['data']['balance'];

        $usd = Currency::convert($balance, $currency);
        $usdUp = ceil($usd);

        if ($usd < 5 && !$usd) {
            $this->sendNotice($usdUp);
        }
    }

    protected function getBalanceInfo()
    {
        $token = Http::acceptJson()
            ->post('https://one-api.bsg.world/api/auth/login', [
                "api_key" => config('services.bsg.api_key')
            ])->json()['bearer'];

        return Http::acceptJson()
            ->withToken($token)
            ->get('https://one-api.bsg.world/api/accounts/balance')->json();
    }

    protected function sendNotice(int $value): void
    {
        if ($ids = config('services.bsg.slack-users')) {
            foreach (explode(',', $ids) as $id) {
                Notification::route('slack', $id)
                    ->notifyNow(new \App\Notifications\BSGBalance($value));
            }
        }

    }
}
