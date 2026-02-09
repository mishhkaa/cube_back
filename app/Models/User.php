<?php

namespace App\Models;

use App\Facades\Slack;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url',
        'google_id',
        'slack_id',
        'fb_access_token',
        'tiktok_access_token',
        'x_token_data',
        'active',
        'permissions'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'fb_access_token',
        'tiktok_access_token',
        'x_token_data'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
        'permissions' => 'array'
    ];

    public function updateSlackId(): static
    {
        if ($slackUser = Slack::getUserByEmail($this->email)){
            $this->update(['slack_id' => $slackUser['id']]);
        }
        return $this;
    }

    public function routeNotificationForSlack(): ?string
    {
        return $this->slack_id;
    }
}
