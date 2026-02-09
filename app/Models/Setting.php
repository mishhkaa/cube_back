<?php

namespace App\Models;

use App\Casts\Serialize;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public $incrementing = false;
    protected $primaryKey = 'key';

    public $timestamps = false;

    protected $casts = [
        'key' => 'string',
        'value' => Serialize::class
    ];

    public static function get(string|array $key): mixed
    {
        $rows = (new static())->newQuery()->whereIn('key', (array)$key)->pluck('value', 'key');
        return is_array($key) ? $rows->toArray() : $rows->first();
    }

    public static function set(string|array $key, $value = null): void
    {
        if (is_string($key)){
            $key = [$key => $value];
        }

        foreach ($key as $k => $v){
            (new static())->newQuery()->updateOrCreate(['key' => $k], ['value' => $v]);
        }
    }

}
