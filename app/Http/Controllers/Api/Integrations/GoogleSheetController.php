<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Models\GoogleSheetAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class GoogleSheetController extends Controller
{
    public function index(): LengthAwarePaginator
    {
        return GoogleSheetAccount::query()
            ->when($this->request->get('id'), function ($query, $id) {
                $query->where('id', $id);
            })
            ->orderBy('id', 'desc')
            ->paginate(20);
    }

    public function store(): array
    {
        return GoogleSheetAccount::query()->create($this->request->post())->toArray();
    }


    public function show(GoogleSheetAccount $google_sheet): array
    {
        return $google_sheet->toArray();
    }

    public function update(GoogleSheetAccount $google_sheet): array
    {
        return tap($google_sheet, function ($google_sheet) {
            $google_sheet->update($this->request->post());
        })->toArray();
    }

    public function destroy(GoogleSheetAccount $google_sheet): \Illuminate\Http\JsonResponse
    {
        $google_sheet->delete();
        return $this->response();
    }
}
