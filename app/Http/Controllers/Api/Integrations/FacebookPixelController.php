<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Models\FacebookPixel;
use App\Services\Conversions\FacebookConversionsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class FacebookPixelController extends Controller
{
    public function index(): LengthAwarePaginator
    {
        return FacebookPixel::query()
            ->when($this->request->query('search'), function (Builder $builder, $value) {
                $builder->where('name', 'like', "%$value%")
                    ->orWhere('id', 'like', "%$value%")
                    ->orWhere('pixel_id', 'like', "%$value%");
            })
            ->orderBy('id', 'desc')
            ->paginate(20);
    }

    public function store(): array
    {
        return FacebookPixel::create($this->request->post())->toArray();
    }

    public function show(FacebookPixel $fb_capi): array
    {
        return $fb_capi->toArray();
    }

    public function update(FacebookPixel $fb_capi): array
    {
        FacebookConversionsService::deleteJsCacheFile($fb_capi->id);
        return tap($fb_capi, function (FacebookPixel $fb_capi) {
            $fb_capi->update($this->request->post());
        })->toArray();
    }

    public function destroy(FacebookPixel $fb_capi): JsonResponse
    {
        FacebookConversionsService::deleteJsCacheFile($fb_capi->id);
        $fb_capi->delete();
        return $this->response();
    }
}
