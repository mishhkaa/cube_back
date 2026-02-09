<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Classes\WaitForCache;
use App\Http\Controllers\Controller;
use App\Models\GoogleAdsAccount;
use App\Services\Conversions\GoogleOfflineConversionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class GoogleAccountController extends Controller
{
    public function index(): LengthAwarePaginator
    {
        return GoogleAdsAccount::query()
            ->orderBy('id', 'desc')
            ->paginate(20);
    }

    public function store(): array
    {
        return GoogleAdsAccount::create($this->request->post())->toArray();
    }

    public function show(GoogleAdsAccount $gads_conversion): array
    {
        return $gads_conversion->toArray();
    }

    public function update(GoogleAdsAccount $gads_conversion): array
    {
        GoogleOfflineConversionsService::clearJsCacheFile($gads_conversion->id);
        return tap($gads_conversion, function (GoogleAdsAccount $account) {
            $account->update($this->request->post());
        })->toArray();
    }

    public function destroy(GoogleAdsAccount $gads_conversion): JsonResponse
    {
        GoogleOfflineConversionsService::clearJsCacheFile($gads_conversion->id);
        $gads_conversion->delete();
        return $this->response();
    }

    public function eventsCount()
    {
        return (new WaitForCache())
            ->setKey('gads-conversions-counts')
            ->setCallback(function (){
                return GoogleAdsAccount::query()
                    ->select(['id'])
                    ->withCount('events')
                    ->get()->pluck('events_count', 'id')->toArray();
            })->run(300, []);
    }
}
