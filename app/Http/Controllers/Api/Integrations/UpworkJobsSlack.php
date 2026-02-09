<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpworkJobsSlack extends Controller
{

    public function index(): LengthAwarePaginator
    {
        return Notice::query()
            ->whereType(Notice::SERVICE_UPWORK_SLACK)
            ->orderBy('name')
            ->paginate(30);

    }

    public function store(): array
    {
        return Notice::create(
            array_merge($this->request->post(), ['type' => Notice::SERVICE_UPWORK_SLACK])
        )->toArray();
    }

    public function show(Notice $upwork_slack): array
    {
        return $upwork_slack->toArray();
    }

    public function update(Notice $upwork_slack)
    {
        return tap($upwork_slack, function (Notice $upwork_slack) {
            $upwork_slack->update(array_merge($this->request->post(), ['type' => Notice::SERVICE_UPWORK_SLACK]));
        });
    }

    public function destroy(Notice $upwork_slack): JsonResponse
    {
        $upwork_slack->delete();
        return $this->response();
    }
}
