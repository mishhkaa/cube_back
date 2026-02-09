<?php

namespace App\Models;

use App\Services\IntegrationsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ScriptBundle extends Model
{
    protected $fillable = ['name', 'utm', 'active'];
    protected $appends = ['integrations'];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        $with = [];
        foreach (IntegrationsService::getIntegrationsModels() as $relation) {
            $with[] = $name = Str::camel(Str::afterLast($relation, '\\'));
            static::resolveRelationUsing($name, static function (self $model) use ($name, $relation) {
                return $model->setHidden(array_merge($model->getHidden(), [$name]))
                    ->morphedByMany($relation, 'scriptable');
            });
        }

        static::addGlobalScope('withIntegrations', static function (Builder $builder) use ($with) {
            $builder->with($with);
        });

        $fn = static function ($account) {
            Cache::forget('bundle_' . $account->id);
        };
        static::updated($fn);
        static::deleted($fn);
    }

    public function integrations(): Attribute
    {
        return Attribute::get(function () {
            return $this->getIntegrations()->map(fn(Model $model) => $model->id);
        });
    }

    public function getIntegrations()
    {
        return collect(IntegrationsService::getIntegrationsModels())
            ->map(static fn($class) => Str::camelClass($class))
            ->mapWithKeys(function ($item) {
                return [$item => $this->$item()->first()];
            })->filter();
    }

    public function setIntegrations($value): void
    {
        if (!$value) {
            return;
        }
        foreach (IntegrationsService::getIntegrationsModels() as $class) {
            $relation = Str::camelClass($class);
            if (!empty($value[$relation])) {
                $this->$relation()->sync([$value[$relation]]);
            } else {
                $this->$relation()->detach();
            }
        }
    }

    public static function cache($id): ?static
    {
        return Cache::remember('bundle_'.$id, 86400, static function () use ($id) {
            return static::find($id);
        });
    }

    public function resolveRouteBinding($value, $field = null): static
    {
        $account = self::cache($value);

        if (!$account) {
            throw (new ModelNotFoundException())->setModel(get_class($this));
        }

        if (!$account->active) {
            abort(403, 'Account not active');
        }

        return $account;
    }
}
