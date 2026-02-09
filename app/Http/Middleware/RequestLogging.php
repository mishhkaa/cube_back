<?php

namespace App\Http\Middleware;

use Closure;
use App\Facades\RequestLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestLogging
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->passLogging($request) || app()->isLocal()) {
            return $next($request);
        }

        RequestLog::save();

        return $next($request);
    }

    public function terminate(Request $request): void
    {
        if ($this->passLogging($request)){
            return;
        }

        RequestLog::saveFull($request);
    }

    protected function passLogging(Request $request): bool
    {
        return in_array($request->method(), ['OPTIONS', 'HEAD']);
    }
}
