<?php

namespace App\Models;

use App\Models\Enums\PixelSource;

class TikTokPixel extends Pixel
{
    protected static function booted(): void
    {
        self::listener();

        static::addGlobalScope('tiktok', static function ($query) {
            $query->where('source', PixelSource::TIKTOK);
        });

        static::creating(static function ($model) {
            $model->source = PixelSource::TIKTOK;
        });
    }

    public static function getSourceName(): string
    {
        return PixelSource::TIKTOK->value . '_conversions';
    }
}
