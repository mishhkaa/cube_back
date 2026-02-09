<?php
namespace App\Classes\Macroable;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\PendingRequest;
use App\Classes\PoolInherit;
use Closure;

class PendingRequestMixin
{
    public function poolInherit(): Closure
    {
        /**
         * @param  callable $callback
         * @return array<\Illuminate\Http\Client\Response>
         */
        return function (callable $callback) {
            /* @var PendingRequest $this */
            $results = [];

            $requests = tap(new PoolInherit($this), $callback)->getRequests();

            foreach ($requests as $key => $item) {
                /** @var PendingRequest|PromiseInterface $item */
                $results[$key] = $item instanceof PendingRequest ? $item->getPromise()?->wait() : $item->wait();
            }

            return $results;
        };
    }
}
