<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Request extends Model
{
    use Prunable;

    public $timestamps = false;

    protected $fillable = [
        'action',
        'method',
        'path',
        'status',
        'message',
        'query',
        'post',
        'time',
        'ip',
        'referer_url',
        'user_agent',
        'user_id'
    ];

    protected $casts = [
        'status' => 'integer',
        'query' => 'array',
        'post' => 'array',
        'time' => 'float',
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable()
    {
        return static::query()->where('created_at', '<', Carbon::now()->subWeek());
    }

    public function setAction(string $name): static
    {
        $this->action = $name;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function setUserId(User|int $user = null): static
    {
        if ($user) {
            $this->user_id = $user->id ?? $user;
        }

        return $this;
    }

    public function saveFull(\Illuminate\Http\Request $request): void
    {
        if ($user = $request->user()) {
            $this->user()->associate($user);
        }

        $this->fill(array_filter([
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer_url' => $request->headers->get('referer'),
            'query' => $request->query(),
            'post' => $request->post(),
            'status' => http_response_code(),
            'time' => round(microtime(true) - LARAVEL_START, 4)
        ]))->save();
    }
}
