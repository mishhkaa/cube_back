<?php

namespace App\Console\Commands\Notifications;

use App\Models\User;
use Carbon\Carbon;
use FbInsight;
use Illuminate\Console\Command;

class FacebookTokenExpiration extends Command
{
    protected $signature = 'notifications:facebook-token-expiration';

    public function handle(): void
    {
        $users = User::query()
            ->whereNotNull('fb_access_token')
            ->where('active', true)
            ->get(['id','slack_id', 'fb_access_token']);

        /** @var User $user */
        foreach ($users as $user) {
            $debug = FbInsight::debugToken($user->fb_access_token);
            if (!empty($debug['error']) || empty($debug['is_valid'])){
                $user->update(['fb_access_token' => null]);
                continue;
            }

            $expired = Carbon::parse($debug['expires_at']);
            $diffDays = $expired->diffInDays(Carbon::now(), true, true);
            if ($diffDays >= 5 && $diffDays < 6){
                $user->notify(new \App\Notifications\FacebookTokenExpiration());
            }
        }
    }
}
