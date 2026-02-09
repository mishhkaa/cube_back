<?php

namespace App\Http\Controllers\Webhooks;

use App\Facades\RequestLog;
use App\Http\Controllers\Controller;
use App\Jobs\Webhooks\ProcessCRMWebhookJob;
use App\Models\DataJob;
use App\Models\Enums\JobStatus;
use Illuminate\Http\JsonResponse;

class CRMController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $dataJob = DataJob::create([
            'queue' => 'crm-webhook',
            'status' => JobStatus::NEW,
            'request_id' => RequestLog::getId()
        ]);

        ProcessCRMWebhookJob::dispatchAfterResponse($this->request->post(), $dataJob);

        return response()->json(['success' => true]);
    }
}
