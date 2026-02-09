<?php

namespace App\Http\Controllers\Api;

use App\Facades\Slack;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    public function index(): LengthAwarePaginator
    {
        return User::query()->paginate(30);
    }

    public function all(): array
    {
        return User::get(["id", "name"])->toArray();
    }

    public function store(): array
    {
        return User::query()->create($this->request->post())->toArray();
    }

    public function show(User $user): array
    {
        return $user->toArray();
    }

    public function update(User $user): array
    {
        return tap($user, function ($user) {
            $user->update($this->request->post());
        })->refresh()->toArray();
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();
        return $this->response();
    }

    public function slackUsers(): array
    {
        return Cache::remember('slack-users', 300, function () {
            $slackUsers = Slack::getUsers() ?: [];
            return collect($slackUsers)
                ->map(function ($user) {
                    return [
                        'label' => $user['real_name'] ?? $user['name'],
                        'value' => $user['profile']['email'] ?? '',
                    ];
                })
                ->filter(function ($user) {
                    return $user['value'];
                })
                ->values()
                ->toArray();
        });
    }
}
