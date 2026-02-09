<?php

namespace App\Http\Middleware;

use App\Models\FacebookPixel;
use App\Models\GoogleAdsAccount;
use App\Models\TikTokPixel;
use App\Models\XPixel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BindEventSourceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $sourceKey = $request->route('source_key');
        $sourceId = $request->route('source_id');

        if (!$sourceKey) {
            throw new BadRequestHttpException('Missing source key in route.');
        }

        $sourceClass = match ($sourceKey) {
            'fb' => FacebookPixel::class,
            'gads' => GoogleAdsAccount::class,
            'tiktok' => TikTokPixel::class,
            'x' => XPixel::class,
            default => null,
        };

        if (!$sourceClass) {
            throw new BadRequestHttpException('Invalid source key: ' . $sourceKey);
        }

        $request->route()?->setParameter('source_key', $sourceClass);

        if ($sourceId !== null) {
            $account = $sourceClass::findOrFail($sourceId);

            $request->route()?->setParameter('source_id', $account);
        }

        return $next($request);
    }
}
