<?php

namespace App\Http\Controllers\Api;

use App\Classes\WaitForCache;
use App\Facades\TikTok;
use App\Http\Controllers\Controller;
use App\Models\User;

class TikTokController extends Controller
{
    public function storeAccessToken(): string
    {
        if (!$authCode = $this->request->query('auth_code')) {
            return 'Auth Code not found';
        }

        if (!$accessToken = TikTok::getAccessToken($authCode)){
            return 'Error get token';
        }

        $state = $this->request->query('state');
        $userId = explode(':', $state)[1] ?? null;

        /** @var User|null $user */
        if (!$userId || (!$user = User::find($userId))){
            return 'User not found';
        }

        $user->update([
            'tiktok_access_token' => $accessToken,
        ]);

        return '<script>window.close()</script>';
    }

    public function getAccessTokenStatus(User $user): array
    {

        if ($user->tiktok_access_token && TikTok::setAccessToken($user->tiktok_access_token)->getUserInfo()){
            return ['status' => true];
        }

        return ['status' => false];
    }

    public function adsAccounts(User $user, WaitForCache $forCache): array
    {
        if ($user->tiktok_access_token) {
            TikTok::setAccessToken($user->tiktok_access_token);
        } else {
            abort(400, 'The account has no access to tiktok');
        }
        try {
            return $forCache->setKey("TikTokAdsAccounts$user->id")
                ->setCallback(fn() => TikTok::getAdvertiserInfo(
                    array_column(TikTok::getAdAccounts(), 'advertiser_id'),
                    ['advertiser_id', 'name', 'status']
                ))
                ->updateIfEmpty()
                ->run(600, []);
        } catch (\Exception $e) {
            abort(400, $e->getMessage());
        }
    }
}
