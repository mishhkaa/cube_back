<?php

namespace App\Classes;

class WebScriptContent
{
    public static function dataLayer() : string
    {
        return file_get_contents(resource_path('js/utils/dataLayerEvent.min.js'));
    }

    public static function helper() :string
    {
        return file_get_contents(resource_path('js/utils/helpers.min.js'));
    }

    public static function getUtmScript(?string $type): string
    {
        $content =  match ($type) {
            'default' => file_get_contents(resource_path('js/utils/utm-default.js')),
            'first-last' => file_get_contents(resource_path('js/utils/utm-first.js')),
            default => ''
        };

        return $content ? preg_replace('/[\r\n]+?( *)+?\s*/','',$content) . PHP_EOL : '';
    }
}
