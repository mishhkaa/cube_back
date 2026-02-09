<?php

namespace App\Models;

use App\Models\Enums\PixelSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

class XPixel extends Pixel
{
    protected static function booted(): void
    {
        self::listener();

        static::addGlobalScope('x', static function (Builder $query) {
            $query->where('source', PixelSource::X)
                ->with('user');
        });

        static::creating(static function ($model) {
            $model->source = PixelSource::X;
        });
    }

    public static function getSourceName(): string
    {
        return PixelSource::X->value . '_conversions';
    }

    public function accessToken(): Attribute
    {
        return Attribute::make(
            get: static fn (string $value) => $value ? json_decode($value, true) : null,
            set: static fn (array $value) => $value ? json_encode($value) : null,
        );
    }

}
