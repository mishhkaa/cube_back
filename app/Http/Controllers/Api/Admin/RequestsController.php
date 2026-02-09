<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RequestsController extends Controller
{
    public function __invoke(): LengthAwarePaginator
    {
        $id = $this->request->get('id');
        return Request::query()
            ->whereNotNull('status')
            ->when($id, function (Builder $query, $id) {
                $query->where('id', $id);
            }, function (Builder $query) {
                $this->defaultQuery($query);
            })
            ->paginate(50);
    }

    protected function defaultQuery(Builder $query): void
    {
        [$dateFrom, $dateTo] = $this->getDate();
        $query
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->orderBy($this->request->get('sort', 'created_at'), $this->request->get('direction', 'desc'))
            ->when($this->request->get('status'), static function (Builder $query, $statuses) {
                $query->where(function (Builder $query) use ($statuses) {
                    foreach (explode(',', $statuses) as $status) {
                        if (Str::contains($status, '-')) {
                            $range = explode('-', $status);
                            $query->whereBetween('status', $range, 'or');
                        }
                    }
                });
            })
            ->when($this->request->get('search'), function ($query, $search) {
                [$columns, $search] = str_contains($search, ':')
                    ? explode(':', $search)
                    : [['path', 'message', 'query', 'post', 'ip'], $search];
                $query->where(function ($query) use ($search, $columns) {
                    foreach ((array)$columns as $column) {
                        $query->where($column, 'like', "%$search%", 'or');
                    }
                });
            });
    }

    public function repeatRequest(Request $request): JsonResponse
    {
        $res = Http::asJson()
            ->withUserAgent($request->user_agent ?: '')
            ->send($request->method, config('app.url').'/'.$request->path, [
                'json' => $request->post,
                'query' => ($request->query ?: []) + ['repeat_request' => true],
            ]);

        return $this->response($res->toException()?->getMessage(), $res->successful());
    }
}
