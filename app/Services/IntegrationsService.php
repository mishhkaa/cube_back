<?php

namespace App\Services;

use App\Actions\IntegrationsCallback\FacebookAction;
use App\Actions\IntegrationsCallback\GoogleAdsAction;
use App\Actions\IntegrationsCallback\IntegrationAction;
use App\Actions\IntegrationsCallback\TikTokAction;
use App\Actions\IntegrationsCallback\XAction;
use App\Contracts\IntegrationServiceInterface;
use App\Contracts\IntegrationWithUserTracking;
use App\Models\FacebookPixel;
use App\Models\GoogleAdsAccount;
use App\Models\GoogleSheetAccount;
use App\Models\ScriptBundle;
use App\Models\TikTokPixel;
use App\Models\TrackingUser;
use App\Models\XPixel;
use App\Services\Conversions\FacebookConversionsService;
use App\Services\Conversions\GoogleOfflineConversionsService;
use App\Services\Conversions\TikTokEventsService;
use App\Services\Conversions\XConversionsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class IntegrationsService
{
    /**
     * @var array<class-string<Model>, class-string<IntegrationServiceInterface>>
     */
    public const array RELATION_MODEL_SERVICE = [
        FacebookPixel::class => FacebookConversionsService::class,
        TikTokPixel::class => TikTokEventsService::class,
        GoogleAdsAccount::class => GoogleOfflineConversionsService::class,
        GoogleSheetAccount::class => GoogleSheetService::class,
        XPixel::class => XConversionsService::class,
    ];

    /**
     * IntegrationsWithUserTracking
     * @var array<class-string<IntegrationServiceInterface>, class-string<IntegrationAction>>
     */
    private const array RELATION_SERVICE_ACTION = [
        FacebookConversionsService::class      => FacebookAction::class,
        TikTokEventsService::class             => TikTokAction::class,
        GoogleOfflineConversionsService::class => GoogleAdsAction::class,
        XConversionsService::class => XAction::class
    ];

    public static function getIntegrationsModels(): array
    {
        return array_keys(self::RELATION_MODEL_SERVICE);
    }

    public static function handleCallbackWebEvent(ScriptBundle $bundle, TrackingUser $user): void
    {
        $integrations = $bundle->getIntegrations();

        foreach ($integrations as $model) {
            $class = get_class($model);
            if (
                ($service = self::RELATION_MODEL_SERVICE[$class] ?? null)
                && is_subclass_of($service, IntegrationWithUserTracking::class)
                && ($action = self::RELATION_SERVICE_ACTION[$service] ?? null)
                && $service::userBelongToIntegration($user)
            ) {
                $method = 'event' . $bundle->id;
                if (method_exists($action, $method)) {
                    if ($data = app()->call($action , ['account' => $model, 'user' => $user], $method)){
                        (new $service())->dispatchEvent($data, $model, ...$action::optionWebDispatchData());
                    }
                }else{
                    Log::info(__CLASS__ . '@' .__METHOD__ . ': method ' . $method . ' for class ' . $action . ' not implemented');
                }
            }
        }
    }
}
