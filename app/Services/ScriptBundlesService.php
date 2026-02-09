<?php

namespace App\Services;

use App\Classes\WebScriptContent;
use App\Models\ScriptBundle;
use Illuminate\Support\Facades\File;

class ScriptBundlesService
{
    public static function deleteJsCacheFile($id): void
    {
        $fileCache = public_path("partners/js/bundle-$id.js");
        File::delete($fileCache);
    }

    public static function getIndexJSContent(): string
    {
        return '<script>'.trim(file_get_contents(resource_path('js/utils/scriptBundle.js'))).'</script>';
    }

    public function getContentJS($id): string
    {
        $bundle = ScriptBundle::find($id);

        if (!$bundle) {
            $content = 'console.log("Please remove this script from your site.")';
        } elseif (!$bundle->active) {
            $content = '';
        } elseif ($bundle->integrations) {
            $content = WebScriptContent::dataLayer();
            $content .= WebScriptContent::helper();
            $content .= WebScriptContent::getUtmScript($bundle->utm);

            $integrationServices = IntegrationsService::RELATION_MODEL_SERVICE;
            foreach ($bundle->getIntegrations() as $model) {
                if (!empty($integrationServices[get_class($model)])) {
                    $content .= app()->call($integrationServices[get_class($model)], [
                        'id' => $model->id,
                        'forBandle' => true,
                    ], 'getContentJS');
                }
            }

            $content .= "window.sessionStorage.setItem('median-events-bundle', '{$id}')" . PHP_EOL;

            $file = resource_path("js/bundles/$id.js");
            if (file_exists($file)) {
                $content .= file_get_contents($file);
            }
        } else {
            $content = '';
        }

        File::put(public_path("partners/js/bundle-$id.js"), $content);
        return $content;
    }
}
