<?php

namespace App\Http\Controllers\Api;

use App\Facades\X;
use App\Http\Controllers\Controller;
use App\Models\User;

class XController extends Controller
{
    public function setToken(): string
    {
        if (!$oauth_token = $this->request->query('oauth_token')) {
            return 'oauth_token not set';
        }

        if (!$oauth_verifier = $this->request->query('oauth_verifier')) {
            return 'oauth_verifier not set';
        }

        $userId = $this->request->query('user_id');

        /** @var User|null $user */
        if (!$userId || (!$user = User::find($userId))) {
            return 'User not found';
        }

        if (!$tokenData = X::getAccessTokenData($oauth_token, $oauth_verifier)){
            return 'error with get token';
        }

        $user->update([
            'x_token_data' => implode(':', $tokenData),
        ]);

        return '<script>window.close()</script>';
    }

    public function getAccessTokenStatus(User $user): array
    {
        if ($user->x_token_data && X::setTokenAndSecret(...explode(':', $user->x_token_data))->getAccounts()){
            return ['status' => true];
        }

        return ['status' => false];
    }

    public function getAuthUrl(string $user): array
    {
        return ['url' => X::getOauthURL($user)];
    }
}
