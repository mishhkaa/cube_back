<?php

namespace App\Http\Controllers\Webhooks;

use App\Facades\RequestLog;
use App\Http\Controllers\Controller;
use App\Jobs\Webhooks\ProcessRingostatWebhookJob;
use App\Models\DataJob;
use App\Models\Enums\JobStatus;
use Illuminate\Http\JsonResponse;

class RingostatController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $dataJob = DataJob::create([
            'queue' => 'ringostat-webhook',
            'status' => JobStatus::NEW,
            'request_id' => RequestLog::getId()
        ]);

        ProcessRingostatWebhookJob::dispatch($this->request->post(), $dataJob);

        return response()->json(['success' => true]);
    }
}
