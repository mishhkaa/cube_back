<?php

namespace App\Models\Traits;

use App\Models\DataJob;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait ImplementConversionAccount
{
    public static function getEventsCountsForPixels(): array
    {
        $source = static::getSourceName();

        $subQuery = DataJob::query()
            ->select(['event', \DB::raw('COUNT(*) AS events_count')])
            ->where('queue', $source)
            ->groupBy('event');

        $table = (new static())->getTable();
        return static::query()
            ->joinSub($subQuery, 'j', function (JoinClause $join) use ($table) {
                $join->on($table . '.id', 'j.event');
            }, type: 'left')
            ->select([$table . '.id', DB::raw('COALESCE(j.events_count, 0) as events_count')])
            ->pluck('events_count', 'id')
            ->toArray();
    }

    public function events(): HasMany
    {
        return $this->hasMany(DataJob::class,'event')
            ->where('queue', static::getSourceName());
    }

    public static function cache($id): ?static
    {
        return Cache::remember(static::getSourceName().'_'.$id, 86400, static function () use ($id) {
            return static::find($id);
        });
    }

    public function resolveRouteBinding($value, $field = null): static
    {
        $account = self::cache($value);

        if (!$account){
            throw (new ModelNotFoundException())->setModel(get_class($this));
        }

        return $account;
    }

    protected static function listener(): void
    {
        $fn = static function ($account) {
            Cache::forget(static::getSourceName().'_'.$account->id);
        };
        static::updated($fn);
        static::deleted($fn);
    }

    protected static function booted(): void
    {
        static::listener();
    }
}