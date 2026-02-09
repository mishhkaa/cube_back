<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Models\XPixel;
use App\Services\Conversions\XConversionsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class XPixelController extends Controller
{
    public function index(): LengthAwarePaginator
    {
        return XPixel::query()
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
        return XPixel::create($this->request->post())->toArray();
    }

    public function show(XPixel $x): array
    {
        return $x->toArray();
    }

    public function update(XPixel $x): array
    {
        XConversionsService::deleteJsCacheFile($x->id);
        return tap($x, function (XPixel $x) {
            $x->update($this->request->post());
        })->toArray();
    }

    public function destroy(XPixel $x): JsonResponse
    {
        XConversionsService::deleteJsCacheFile($x->id);
        $x->delete();
        return $this->response();
    }
}
