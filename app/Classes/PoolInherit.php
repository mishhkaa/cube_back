<?php

namespace App\Classes;

use Illuminate\Http\Client\PendingRequest;

class PoolInherit
{
    protected array $pool = [];

    public function __construct(protected PendingRequest $client)
    {
    }

    public function as(string $key): PendingRequest
    {
        return $this->pool[$key] = $this->asyncRequest();
    }

    protected function asyncRequest(): PendingRequest
    {
        return clone $this->client->async();
    }

    public function getRequests(): array
    {
        return $this->pool;
    }

    public function __call($method, $parameters)
    {
        return $this->pool[] = $this->asyncRequest()->$method(...$parameters);
    }
}
