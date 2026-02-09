<?php

namespace App\Http\Controllers\Api;

use App\Classes\WaitForCache;
use App\Contracts\ConversionAccountInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventsController extends Controller
{
    /**
     * @param  class-string<ConversionAccountInterface>  $source_key
     * @return array
     */
    public function eventsCount(string $source_key): array
    {
        return (new WaitForCache())
            ->setKey($source_key::getSourceName() . '_counts')
            ->setCallback(function () use ($source_key){
                return $source_key::getEventsCountsForPixels();
            })->run(300, []);
    }

    public function events(string $source_key, ConversionAccountInterface $sourceAccount): Collection
    {
        return $sourceAccount->events()->distinct()->pluck('action');
    }

    public function countByEvents(string $source_key, ConversionAccountInterface $sourceAccount): array
    {
        if (!$events = $this->request->get('events')){
            return [];
        }
        $events = explode(',', $events);

        [$dateFrom, $dateTo] = $this->getDate();

        $data = [];

        $counts = $sourceAccount->events()
            ->select(['action', DB::raw('count(*) as total')])
            ->when($events, function (Builder $builder, $events) {
                $builder->whereIn('action', $events);
            })
            ->when($this->request->query('status'), function (Builder $builder, $value) {
                $builder->whereIn('status', $value);
            })
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when($this->request->get('onlyFromAds'), function ($query) {
                $this->setAdditionFilter($query);
            })
            ->groupBy('action')
            ->pluck('total', 'action')
            ->toArray();

        foreach ($events as $event) {
            $data[] = [
                'count' => $counts[$event] ?? 0,
                'event' => $event
            ];
        }
        return $data;
    }

    private function setAdditionFilter(Builder $builder): void
    {
        $sourceKey = $this->request->route('source_key');

        if ($sourceKey === 'tiktok') {
            $builder->where('payload', 'like', '%"ttclid":%');
        }
        if ($sourceKey === 'fb') {
            $builder->where('payload', 'like', '%"fbc":%');
        }
    }

    public function countByDaysAndEvents(string $source_key, ConversionAccountInterface $sourceAccount): array
    {
        if (!$events = $this->request->get('events')){
            return ['data' => []];
        }
        $events = explode(',', $events);

        $days = array_map(static function (Carbon $date) {
            return $date->format('Y-m-d');
        }, CarbonPeriod::between(...$this->getDate())->toArray());

        $rows = $sourceAccount->events()
            ->select(['action', DB::raw('count(*) as total'), DB::raw('DATE(created_at) as date')])
            ->whereIn('action', $events)
            ->when($this->request->query('status'), function (Builder $builder, $value) {
                $builder->where('status', $value);
            })
            ->when($this->request->get('onlyFromAds'), function ($query) {
                $this->setAdditionFilter($query);
            })
            ->whereIn(DB::raw('DATE(created_at)'), $days)
            ->groupBy('date', 'action')
            ->get();

        $data = [];

        foreach ($days as $day) {
            $data[$day] = $data[$day] ?? ['date' => $day];
            foreach ($events as $event) {
                $data[$day][$event] = 0;
            }
        }

        foreach ($rows as $row) {
            $data[$row->date] = $data[$row->date] ?? ['date' => $row->date];
            $data[$row->date][$row->action] = $row->total;
        }

        return array_values($data);
    }

    public function list(string $source_key, ConversionAccountInterface $sourceAccount): LengthAwarePaginator|array
    {
        if (!$events = $this->request->get('events')){
            return new \Illuminate\Pagination\LengthAwarePaginator(collect(), 0, 20);
        }
        $events = explode(',', $events);

        [$dateFrom, $dateTo] = $this->getDate();

        return $sourceAccount->events()
            ->whereIn('action', $events)
            ->when($this->request->query('status'), function (Builder $builder, $value) {
                $builder->where('status', $value);
            })
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when($this->request->get('onlyFromAds'), function ($query) {
                $this->setAdditionFilter($query);
            })
            ->orderBy(
                $this->request->query('sort', 'created_at'),
                $this->request->get('direction', 'desc')
            )
            ->paginate(35);
    }
}
