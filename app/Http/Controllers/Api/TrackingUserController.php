<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrackingUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class TrackingUserController extends Controller
{
    public function __invoke(): LengthAwarePaginator
    {
        return TrackingUser::query()
            ->when($this->request->query('search'), function (Builder $builder, $value) {
                $builder->where(function (Builder $q) use ($value) {
                    $q->where('id', 'like', "%{$value}%")
                        ->orWhere('data', 'like', "%{$value}%");
                });
            })
            ->orderBy(
                $this->request->query('sort', 'created_at'),
                $this->request->query('direction', 'desc')
            )
            ->paginate(30);
    }
}
