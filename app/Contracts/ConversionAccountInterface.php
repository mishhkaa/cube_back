<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

interface ConversionAccountInterface
{
    public static function getSourceName(): string;

    public function events(): HasMany;

    public static function getEventsCountsForPixels(): array;

    public static function cache($id): ?static;
}
