<?php

namespace App\Providers;

use App\Classes\ApiClients\FbInsightClient;
use App\Classes\ApiClients\GoogleAdsClient;
use App\Classes\ApiClients\MailchimpClient;
use App\Classes\ApiClients\PipeDriveClient;
use App\Classes\ApiClients\SlackClient;
use App\Classes\ApiClients\TikTokClient;
use App\Classes\ApiClients\XClient;
use App\Classes\Currency\FreeCurrency;
use App\Classes\Macroable\BuilderMixin;
use App\Classes\Macroable\CarbonPeriodMixin;
use App\Classes\Macroable\PendingRequestMixin;
use App\Classes\SlackNotification\NotificationRouterChannel;
use App\Contracts\CurrencyInterface;
use App\Models\Request;
use App\Models\TrackingUser;
use Carbon\CarbonPeriod;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public array $singletons = [
        'currency' => CurrencyInterface::class,
        'pipedrive' => PipeDriveClient::class,
        'fbinsight' => FbInsightClient::class,
        'mailchimp' => MailchimpClient::class,
        'slack' => SlackClient::class,
        'tiktok-api' => TikTokClient::class,
        'request.log' => Request::class,
        'x-api' => XClient::class,
        'google-ads' => GoogleAdsClient::class
    ];

    public function register(): void
    {
    }

    public function boot(): void
    {
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        Notification::resolved(static function (ChannelManager $service) {
            $service->extend('slack', fn ($app) => new NotificationRouterChannel($app));
        });

        Builder::mixin(new BuilderMixin());

        PendingRequest::mixin(new PendingRequestMixin());

        CarbonPeriod::mixin(new CarbonPeriodMixin());

        Str::macro('camelClass', static fn(string $class) => Str::camel(Str::afterLast($class, '\\')));

        $this->app->singleton(CurrencyInterface::class, fn() => new FreeCurrency());

        Cache::macro('lockTrackingUser', function (TrackingUser|string $user, Closure $closure) {
            return Cache::lock('external_user_id_' . ($user->id ?? $user), 2)->block(2, $closure);
        });
    }
}
