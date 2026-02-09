<?php

namespace App\Http\Controllers\Api;

use App\Classes\WaitForCache;
use App\Facades\FbInsight;
use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;

class FacebookController extends Controller
{
    public function adsAccounts(User $user,WaitForCache $forCache): array
    {
        if ($user->fb_access_token){
            FbInsight::setToken($user->fb_access_token);
        }else{
            abort(400, 'The account has no access to facebook');
        }
        try {
            return $forCache->setKey("FBAdsAccounts$user->id")
                ->setCallback(fn() => FbInsight::getAccounts())
                ->updateIfEmpty()
                ->run(100, []);
        }catch (Exception $e){
            abort(400, $e->getMessage());
        }
    }

    public function storeAccessToken(): string
    {
        if (!$code = $this->request->query('code')){
            return 'Code not found';
        }

        if (!$token = FbInsight::getAccessToken($code)){
            return 'Error get token';
        }

        $state = $this->request->query('state');
        $userId = explode(':', $state)[1] ?? null;

        /** @var User|null $user */
        if (!$userId || (!$user = User::find($userId))){
            return 'User not found';
        }

        $user->update([
            'fb_access_token' => $token,
        ]);

        return '<script>window.close()</script>';
    }

    public function getAccessTokenExpire(User $user): array
    {
        if (!$user->fb_access_token){
            return ['expire' => 0];
        }

        try {
            $data = FbInsight::debugToken($user->fb_access_token);
        }catch (Exception){
            return ['expire' => 0];
        }

        return ['expire' => !empty($data['is_valid']) ? $data['expires_at'] : 0];
    }
}
