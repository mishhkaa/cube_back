<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Логін по email та паролю. Повертає Sanctum token та user.
     */
    public function login(Request $request): JsonResponse|array
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $user = Auth::user();
        if (!$user->active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => [__('Доступ заборонено.')],
            ]);
        }

        $user->tokens()->where('name', 'api')->delete();
        $token = $user->createToken('api')->plainTextToken;

        return [
            'token' => $token,
            'user' => $user->toArray(),
        ];
    }

    public function user(): array|null
    {
        $user = $this->request->user();
        return $user ? $user->toArray() : null;
    }

    public function googleAuthUrl(): JsonResponse|array
    {
//        Auth::guard('web')->loginUsingId(1, true);
        return ['url' => Socialite::driver('google')->stateless()->redirect()->getTargetUrl()];
    }

    public function logout(): JsonResponse
    {
        if ($this->request->user()) {
            $this->request->user()->currentAccessToken()->delete();
        }

        return $this->response();
    }

    public function loginWithGoogle(): JsonResponse|array
    {
        /** @var \Laravel\Socialite\Contracts\User $googleUser */
        if (!$googleUser = Socialite::driver('google')->stateless()->user()){
            abort(401);
        }

        $user = User::query()
            ->where('email', $googleUser->getEmail())
            ->where('active', true)
            ->first();

        if (!$user){
            return $this->response('Access is not open to you', false);
        }

        Auth::guard('web')->loginUsingId($user->id, true);

        $user->update([
            'name' => $googleUser->getName(),
            'avatar_url' => $googleUser->getAvatar(),
            'google_id' => $googleUser->getId(),
        ]);

        if (!$user->slack_id){
            $user->updateSlackId();
        }

        return tap($user, function ($findUser) use ($googleUser) {
            $findUser->update([
                'name' => $googleUser->getName(),
                'avatar_url' => $googleUser->getAvatar(),
                'google_id' => $googleUser->getId(),
            ]);
        })->refresh()->toArray();
    }

    public function getCsrf(): string
    {
        return csrf_token() ?? '';
    }
}
