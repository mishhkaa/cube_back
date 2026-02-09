<?php

namespace App\Models;

use App\Models\Enums\PixelSource;

class FacebookPixel extends Pixel
{
    protected static function booted(): void
    {
        self::listener();
        static::addGlobalScope('facebook', static function ($query) {
            $query->where('source', PixelSource::FACEBOOK);
        });

        static::creating(static function ($model) {
            $model->source = PixelSource::FACEBOOK;
        });
    }

    public static function getSourceName(): string
    {
        return PixelSource::FACEBOOK->value . '_conversions';
    }
}
