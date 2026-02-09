<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataJob;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class JobsController extends Controller
{
    public function jobs(): LengthAwarePaginator
    {
        return DataJob::query()
            ->whereBetween('created_at', $this->getDate())
            ->orderBy($this->request->get('sort', 'created_at'), $this->request->get('direction', 'desc'))
            ->when($this->request->get('search'), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $columns = ['payload', 'response', 'message'];
                    foreach ($columns as $column) {
                        $query->where($column, 'like', "%$search%", 'or');
                    }
                });
            })->when($this->request->get('queue'), function ($query, $queue) {
                $query->where('queue', $queue);
            })->when($this->request->get('status'), function ($query, $status) {
                $query->where('status', $status);
            })->paginate(50);
    }

    public function queues(): array
    {
        return DataJob::query()->distinct('queue')->pluck('queue')->toArray();
    }
}
