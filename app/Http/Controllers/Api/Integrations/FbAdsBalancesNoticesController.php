<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class FbAdsBalancesNoticesController extends Controller
{
    public function index(): LengthAwarePaginator
    {
        return Notice::query()
            ->whereType(Notice::SERVICE_FB_ADS_BALANCES)
            ->orderBy('name')
            ->paginate(30);
    }

    public function store(): array
    {
        return Notice::create(
            array_merge($this->request->post(), ['type' => Notice::SERVICE_FB_ADS_BALANCES])
        )->toArray();
    }

    public function update(Notice $fb_ads_balance)
    {
        return tap($fb_ads_balance, function (Notice $fb_ads_balance) {
            $fb_ads_balance->update(array_merge($this->request->post(), ['type' => Notice::SERVICE_FB_ADS_BALANCES]));
        });
    }

    public function destroy(Notice $fb_ads_balance): JsonResponse
    {
        $fb_ads_balance->delete();
        return $this->response();
    }
}
