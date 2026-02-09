<?php

namespace App\Http\Controllers\Api;

use App\Console\Commands\FacebookInsightsBigQuery;
use App\Console\Commands\GoogleAdsToBigQuery;
use App\Console\Commands\TikTokAdToBigQuery;
use App\Http\Controllers\Controller;
use App\Models\AdSource;
use App\Models\AdSourcesEvent;
use Artisan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdsSourceController extends Controller
{
    public function index(): LengthAwarePaginator
    {
        return AdSource::query()->orderBy('name' )->paginate(20);
    }

    public function getBigQuerySchema(AdSource $ads_source): array
    {
        return $ads_source->getBigQueryTable()->info()['schema']['fields'] ?? [];
    }

    public function store(Request $request): array
    {
        return AdSource::query()->create($request->post())->toArray();
    }

    public function show(AdSource $ads_source): array
    {
        return $ads_source->toArray();
    }

    public function update(AdSource $ads_source): array
    {
        return tap($ads_source, function (AdSource $source){
            $source->update($this->request->only(['settings', 'project_id', 'user_id', 'active', 'accounts']));
        })->toArray();
    }

    public function destroy(AdSource $ads_source): JsonResponse
    {
        $table = $ads_source->getBigQueryTable();
        if ($table->exists()){
            $table->delete();
        }

        $ads_source->delete();
        return $this->response();
    }

    public function lastTimeDownload(string $ads_source): int
    {
        $lastTime = AdSourcesEvent::query()
            ->where('ad_source_id', $ads_source)
            ->orderBy('created_at', 'desc')
            ->first('created_at')->created_at ?? null;
        return $lastTime ? strtotime($lastTime) : 1;
    }

    public function downloadData(AdSource $ads_source): JsonResponse
    {
        if ((!$dateFrom = $this->request->get('from')) || (!$dateTo = $this->request->get('to')) ) {
            return $this->response('dates incorrect', false);
        }

        $SourceClass = [
            'fb' => FacebookInsightsBigQuery::class,
            'tiktok' => TikTokAdToBigQuery::class,
            'gads' => GoogleAdsToBigQuery::class,
        ][$ads_source->platform] ?? null;

        if (!$SourceClass){
            return $this->response('The platform has not yet been implemented', false);
        }

        dispatch(static function () use ($dateTo, $dateFrom, $ads_source, $SourceClass) {
            Artisan::call($SourceClass, [
                '--accountId' => $ads_source->id,
                '--date' => $dateFrom . '...' . $dateTo
            ]);
        })->afterResponse();

        return $this->response();
    }

    public function log(string $ads_source): LengthAwarePaginator
    {
        return AdSourcesEvent::query()
            ->where('ad_source_id', $ads_source)
            ->orderBy('id', 'desc')
            ->paginate(30);
    }
}
