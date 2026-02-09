<?php

namespace App\Http\Controllers;

use App\Facades\RequestLog;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __construct(protected Request $request)
    {
    }

    protected function response(?string $message = null, bool $success = true): JsonResponse
    {
        if (!$success){
            RequestLog::setMessage($message);
        }
        return new JsonResponse(['success' => true, 'message' => $message], $success ? 200 : 400);
    }

    protected function getDate($from = null, $to = null): array
    {
        if ($from && $to){
            return [
                Carbon::parse($from)->format('Y-m-d 00:00:00'),
                Carbon::parse($to)->format('Y-m-d 23:23:59')
            ];
        }

        $time1 = $this->request->get('time1');
        $time2 = $this->request->get('time2');

        if ($time1 && $time2){
            return [
                date('Y-m-d H:i:s', (int)$time1),
                date('Y-m-d H:i:s', (int)$time2),
            ];
        }

        return [
            Carbon::parse($this->request->get('from'))->format('Y-m-d 00:00:00'),
            Carbon::parse($this->request->get('to'))->format('Y-m-d 23:23:59'),
        ];
    }
}
