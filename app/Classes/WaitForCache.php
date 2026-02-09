<?php

namespace App\Classes;

use Closure;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class WaitForCache
{
    private string $key;
    private Closure $callback;
    private bool $updateIfEmpty = false;

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function setCallback(Closure $closure): static
    {
        $this->callback = $closure;

        return $this;
    }

    public function clear(): static
    {
        if (isset($this->key)){
            Cache::forget($this->key);
            Cache::forget($this->getProcessCacheKey());
        }

        return $this;
    }

    public function updateIfEmpty(): static
    {
        $this->updateIfEmpty = true;

        return $this;
    }

    public function run(int $ttl, $returnDefault = null)
    {
        if (!isset($this->key, $this->callback)){
            return $returnDefault;
        }

        if (Cache::has($this->key)){
            $data = Cache::get($this->key);
            if ($this->updateIfEmpty){
                if (!empty($data)){
                    return $data;
                }

                Cache::forget($this->key);
            }else{
                return $data;
            }
        }

        $processCacheKey = $this->getProcessCacheKey();

        if (!Cache::has($processCacheKey)){
            Cache::add($processCacheKey, 1 , $ttl);
            return Cache::remember($this->key, $ttl, fn() => ($this->callback)());
        }

        try {
            return retry(
                ceil($ttl/5),
                function (){
                    if (Cache::has($this->key)){
                        return Cache::get($this->key);
                    }
                    throw new RuntimeException('Cache key not found: ' . $this->key);
                },
                5000
            );
        }catch (Throwable $throwable){
            report($throwable);
        }

        return $returnDefault;
    }

    protected function getProcessCacheKey(): string
    {
        return 'process_' . $this->key;
    }
}
