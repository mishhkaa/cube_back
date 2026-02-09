<?php

use App\Http\Controllers\Api\Admin\ActionsController;
use App\Http\Controllers\Api\Admin\JobsController;
use App\Http\Controllers\Api\Admin\LogsController;
use App\Http\Controllers\Api\Admin\RequestsController;
use App\Http\Controllers\Api\Admin\RequestsStatsController;
use App\Http\Controllers\Api\Admin\SummaryController;
use App\Http\Controllers\Api\AdsSourceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EventsController;
use App\Http\Controllers\Api\FacebookController;
use App\Http\Controllers\Api\Integrations\CsdProjectController;
use App\Http\Controllers\Api\Integrations\FbAdsBalancesNoticesController;
use App\Http\Controllers\Api\Integrations\FacebookPixelController;
use App\Http\Controllers\Api\Integrations\GoogleAccountController;
use App\Http\Controllers\Api\Integrations\GoogleSheetController;
use App\Http\Controllers\Api\Integrations\ScriptBundleController;
use App\Http\Controllers\Api\Integrations\TikTokPixelController;
use App\Http\Controllers\Api\Integrations\UpworkJobsSlack;
use App\Http\Controllers\Api\Integrations\XPixelController;
use App\Http\Controllers\Api\TikTokController;
use App\Http\Controllers\Api\UserController;
use \App\Http\Controllers\Api\XController;
use App\Http\Middleware\BindEventSourceMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('get-csrf', [AuthController::class, 'getCsrf']);
Route::post('login', [AuthController::class, 'login']);

Route::get('google-auth-url', [AuthController::class, 'googleAuthUrl']);
Route::get('login-with-google', [AuthController::class, 'loginWithGoogle']);
Route::get('facebook/set-token', [FacebookController::class, 'storeAccessToken']);
Route::get('tiktok/set-token', [TikTokController::class, 'storeAccessToken']);
Route::get('x/set-token', [XController::class, 'setToken']);

Route::middleware('auth:sanctum')->group(static function () {
    Route::get('user', [AuthController::class, 'user']);
    Route::get('logout', [AuthController::class, 'logout']);

    Route::prefix('admin')->group(static function () {
        Route::get('/', SummaryController::class);
        Route::get('run-action', ActionsController::class);
        Route::get('requests', RequestsController::class);
        Route::get('repeat-request/{request}', [RequestsController::class, 'repeatRequest']);
        Route::get('jobs', [JobsController::class, 'jobs']);
        Route::get('queues', [JobsController::class, 'queues']);
        Route::get('logs', [LogsController::class, 'index']);
        Route::delete('log', [LogsController::class, 'destroy']);
        Route::get('tracking-users', \App\Http\Controllers\Api\TrackingUserController::class);
        Route::get('requests-by-ip', [RequestsStatsController::class, 'byIp']);
        Route::get('requests-by-country', [RequestsStatsController::class, 'byCountry']);
    });

    Route::prefix('dashboard')->group(static function () {
        Route::get('last-notices', [DashboardController::class, 'lastNotices']);
    });

    Route::prefix('facebook')->group(static function () {
        Route::get('ads-accounts/{user}', [FacebookController::class, 'adsAccounts']);
        Route::get('access-token-expire/{user}', [FacebookController::class, 'getAccessTokenExpire']);
    });

    Route::get('x/access-status/{user}', [XController::class, 'getAccessTokenStatus']);
    Route::get('x/auth-url/{user}', [XController::class, 'getAuthUrl']);

    Route::prefix('tiktok')->group(static function () {
        Route::get('ads-accounts/{user}', [TikTokController::class, 'adsAccounts']);
        Route::get('access-status/{user}', [TikTokController::class, 'getAccessTokenStatus']);
    });

    Route::prefix('google-ads')->group(static function () {
        Route::get('ads-accounts', [\App\Http\Controllers\Api\GoogleAdsController::class, 'adsAccounts']);
    });

    Route::get('ads-sources/{ads_source}/schema', [AdsSourceController::class, 'getBigQuerySchema']);
    Route::get('ads-sources/{ads_source}/log', [AdsSourceController::class, 'log']);
    Route::get('ads-sources/{ads_source}/last-time-download', [AdsSourceController::class, 'lastTimeDownload']);
    Route::get('ads-sources/{ads_source}/download-data', [AdsSourceController::class, 'downloadData']);

    Route::apiResource('ads-sources', AdsSourceController::class);

    Route::get('users/all', [UserController::class, 'all']);
    Route::get('users/from-slack', [UserController::class, 'slackUsers']);
    Route::apiResource('users', UserController::class);

    Route::prefix('integrations')->group(static function () {
        Route::apiResource('fb-capi', FacebookPixelController::class);
        Route::apiResource('tiktok', TikTokPixelController::class);
        Route::apiResource('upwork-slack', UpworkJobsSlack::class);
        Route::apiResource('google-sheets', GoogleSheetController::class);
        Route::apiResource('gads-conversions', GoogleAccountController::class);
        Route::apiResource('fb-ads-balances', FbAdsBalancesNoticesController::class);
        Route::get('script-bundles/integrations-accounts', [ScriptBundleController::class, 'integrationsAccounts']);
        Route::get('script-bundles/js-content', [ScriptBundleController::class, 'jsContent']);
        Route::apiResource('script-bundles', ScriptBundleController::class);
        Route::apiResource('x', XPixelController::class);

        Route::prefix('events-{source_key}')
            ->middleware(BindEventSourceMiddleware::class)
            ->group(static function () {
            Route::get('/', [EventsController::class, 'eventsCount']);
            Route::prefix('{source_id}')->group(static function () {
                Route::get('/', [EventsController::class, 'events']);
                Route::get('list', [EventsController::class, 'list']);
                Route::get('countByDaysAndEvents', [EventsController::class, 'countByDaysAndEvents']);
                Route::get('countByEvents', [EventsController::class, 'countByEvents']);
            });
        });

        Route::get('csd-projects/refresh-spend', [CsdProjectController::class, 'refreshSpendAll']);
        Route::get('csd-projects/{name}', [CsdProjectController::class, 'usedName']);
        Route::get('csd-projects/{csdProject}/refresh-spend', [CsdProjectController::class, 'refreshSpend']);
        Route::get('csd-projects/{csdProject}/log', [CsdProjectController::class, 'getLog']);
        Route::apiResource('csd-projects', CsdProjectController::class)->only(['index', 'update', 'store']);
    });
});
