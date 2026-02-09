<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Models\TikTokPixel;
use App\Services\Conversions\TikTokEventsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class TikTokPixelController extends Controller
{
    public function index(): LengthAwarePaginator
    {
        return TikTokPixel::query()
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
        return TikTokPixel::create($this->request->post())->toArray();
    }

    public function show(TikTokPixel $tiktok): array
    {
        return $tiktok->toArray();
    }

    public function update(TikTokPixel $tiktok): array
    {
        TikTokEventsService::deleteJsCacheFile($tiktok->id);
        return tap($tiktok, function (TikTokPixel $tiktok) {
            $tiktok->update($this->request->post());
        })->toArray();
    }

    public function destroy(TikTokPixel $tiktok): JsonResponse
    {
        TikTokEventsService::deleteJsCacheFile($tiktok->id);
        $tiktok->delete();
        return $this->response();
    }
}
