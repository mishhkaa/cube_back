<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Console\Commands\CsdProjectsRefreshSpendsCommand;
use App\Http\Controllers\Controller;
use App\Models\BigQuery\CsdTable;
use App\Models\CsdProject;
use Artisan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class CsdProjectController extends Controller
{
    public function index(): LengthAwarePaginator
    {
        return CsdProject::query()->paginate(20);
    }

    public function update(CsdProject $csd_project): CsdProject
    {
        return tap($csd_project, function ($csdProject) {
            $csdProject->update($this->request->post());
        });
    }

    public function store(): CsdProject
    {
        return CsdProject::create($this->request->post());
    }

    public function usedName(string $name): array
    {
        return [
            'used' => CsdProject::query()->where('name', $name)->exists()
        ];
    }

    public function refreshSpend(CsdProject $csdProject): JsonResponse
    {
        $dates = $this->request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
        ]);
        [$from, $to] = [$dates['from'], $dates['to']];
        dispatch(static function () use ($from, $to, $csdProject) {
            Artisan::call(CsdProjectsRefreshSpendsCommand::class, [
                '--accountId' => $csdProject->id,
                '--date' => $from . '...' . $to
            ]);
        })->afterResponse();

        return $this->response();
    }

    public function refreshSpendAll(): JsonResponse
    {
        $dates = $this->request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
        ]);
        [$from, $to] = [$dates['from'], $dates['to']];
        dispatch(static function () use ($from, $to) {
            Artisan::call(CsdProjectsRefreshSpendsCommand::class, [
                '--date' => $from . '...' . $to
            ]);
        })->afterResponse();

        return $this->response();
    }

    public function getLog(CsdProject $csdProject): array
    {
        $this->request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
        ]);
        return CsdTable::
            whereBetween('date', [$this->request->get('from'), $this->request->get('to')])
            ->where('name', $csdProject->name)
            ->when($this->request->get('platform'), fn(Builder $builder, $platform) => $builder->where('platform', $platform))
            ->when($this->request->get('ad_account_id'), fn(Builder $builder, $accountId) => $builder->where('ad_account_id', $accountId))
            ->when($this->request->get('group'), function (Builder $builder){
                $builder->groupBy('platform')
                    ->selectRaw('`platform`, SUM(`spend`) as spend');
            })
            ->getRaw();
    }
}
