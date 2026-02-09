<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataJob;
use App\Models\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SummaryController extends Controller
{
    public function __invoke(): array
    {
        $dateFrom = Carbon::parse($this->request->get('from'))->format('Y-m-d 00:00:00');
        $dateTo = Carbon::parse($this->request->get('to'))->format('Y-m-d 23:23:59');

        return [
            'requests' => $this->getRequestsData($dateFrom, $dateTo),
            'jobs' => $this->getJobsData($dateFrom, $dateTo),
        ];
    }

    protected function getRequestsData($dateFrom, $dateTo): array
    {
        $step = $this->request->get('step', 900);

        $rows = Request::query()
            ->select([
                DB::raw('FROM_UNIXTIME(UNIX_TIMESTAMP(created_at) - MOD(UNIX_TIMESTAMP(created_at), '.$step.')) AS `time_step`'),
                DB::raw('COUNT(*) AS `all`'),
                DB::raw('COUNT(CASE WHEN `status` >= 400 THEN 1 END) AS `error`'),
            ])
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('time_step')
            ->orderBy('time_step')
            ->getRaw();

        return [
            'data' => $rows,
            'count' => Request::query()
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->count()
        ];
    }

    protected function getJobsData($dateFrom, $dateTo): array
    {
        $queues = DataJob::query()->distinct('queue')->pluck('queue');
        $counts = [];
        foreach ($queues as $queue) {
            $counts[$queue] = [
                'name' => $queue,
                'count' => 0,
                'data' => [
                    'new' => ['name' => 'New', 'count' => 0],
                    'processing' => ['name' => 'Processing', 'count' => 0],
                    'done' => ['name' => 'Done', 'count' => 0],
                    'warning' => ['name' => 'Warning', 'count' => 0],
                    'error' => ['name' => 'Error', 'count' => 0],
                ]
            ];
        }

        $rows = DataJob::query()
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->select(['queue', 'status', DB::raw('COUNT(*) as `count`')])
            ->groupBy(['queue', 'status'])
            ->getRaw();

        $count = 0;
        foreach ($rows as $row) {
            $count += $row->count;
            $counts[$row->queue]['count'] += $row->count;
            $counts[$row->queue]['data'][$row->status]['count'] = $row->count;
        }

        return [
            'data' => array_values($counts),
            'count' => $count
        ];
    }
}
