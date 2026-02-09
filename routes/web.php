<?php

use App\Http\Controllers\FacebookAccountsReportController;
use App\Http\Controllers\Integrations\CallbackController;
use App\Http\Controllers\Integrations\FbCApiController;
use App\Http\Controllers\Integrations\GAdsConversionsController;
use App\Http\Controllers\Integrations\GoogleSheetsController;
use App\Http\Controllers\Integrations\ScriptBundleController;
use App\Http\Controllers\Integrations\TikTokEventController;
use App\Http\Controllers\Integrations\XEventController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\Webhooks\AdsQuizController;
use App\Http\Controllers\Webhooks\CRMController;
use App\Http\Controllers\Webhooks\FacebookLeadsAdsController;
use App\Http\Controllers\Webhooks\HelpCrunchController;
use App\Http\Controllers\Webhooks\RingostatController;
use App\Http\Controllers\Webhooks\SlackController;
use App\Http\Controllers\Webhooks\TelegramController;
use App\Http\Middleware\RequestLogging;
use Illuminate\Support\Facades\Route;

Route::get('facebook-accounts', FacebookAccountsReportController::class);
Route::get('facebook-accounts/median-cds', [FacebookAccountsReportController::class, 'reportForMedianCSD']);

Route::get('looker-studio/fb-accounts', [FacebookAccountsReportController::class, 'getAccounts']);

// RequestLogging
Route::middleware(RequestLogging::class)->group(static function () {
    Route::prefix('partners')->group(static function () {
        Route::prefix('fbcapi')->group(static function () {
            Route::post('/', [FbCApiController::class, 'siteEvent']);
            Route::match(['get', 'post'], '{account}/crm/{leadId?}/{eventName?}/{nameCrm?}', [FbCApiController::class, 'crmEvent']);
            Route::match(['get', 'post'], '{account}/{externalId?}', [FbCApiController::class, 'serverEvent']);
        });

        Route::any('callback/{account}', [CallbackController::class, 'handle']);

        Route::post('google-sheets/{account}', [GoogleSheetsController::class, 'handle']);

        Route::prefix('gads-conversions/{gadsAccount}')->group(static function () {
            Route::match(['post', 'get'], '/', [GAdsConversionsController::class, 'event']);
            Route::get('conversions.csv', [GAdsConversionsController::class, 'conversions']);
        });

        Route::post('tiktok-events/{pixel}', [TikTokEventController::class, 'webEvent']);
        Route::post('x-events/{pixel}', [XEventController::class, 'event']);

        Route::prefix('js')->group(static function () {
            Route::get('fb-events-{id}.js', [FbCApiController::class, 'jsRender']);
            Route::get('tiktok-events-{id}.js', [TikTokEventController::class, 'jsRender']);
            Route::get('google-sheets-{id}.js', [GoogleSheetsController::class, 'jsRender']);
            Route::get('gads-conversions-{id}.js', [GAdsConversionsController::class, 'jsRender']);
            Route::get('x-events-{id}.js', [XEventController::class, 'jsRender']);
            Route::get('bundle-{id}.js', [ScriptBundleController::class, 'jsRender']);
        });
    });

    Route::prefix('webhooks')->group(static function () {
        Route::post('pipedrive', CRMController::class);
        Route::post('facebook-leads-ads', FacebookLeadsAdsController::class);
        Route::post('slack', SlackController::class);
        Route::post('help-crunch', HelpCrunchController::class);
        Route::post('telegram', TelegramController::class);
        Route::post('ringostat', RingostatController::class);
        Route::post('ads-quiz', AdsQuizController::class);
    });

    Route::any('tour-tech/crm', \App\Http\Controllers\Clients\TourTechCrmOrdersController::class);

    Route::any('test', TestController::class);
});

Route::fallback(static fn() => abort(404));
