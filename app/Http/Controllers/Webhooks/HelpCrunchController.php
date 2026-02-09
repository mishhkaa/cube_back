<?php

namespace App\Http\Controllers\Webhooks;

use App\Facades\RequestLog;
use App\Http\Controllers\Controller;
use App\Jobs\Webhooks\ProcessHelpCrunchWebhookJob;
use App\Models\DataJob;
use App\Models\Enums\JobStatus;
use Illuminate\Http\JsonResponse;

class HelpCrunchController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $dataJob = DataJob::create([
            'queue' => 'helpcrunch-webhook',
            'status' => JobStatus::NEW,
            'request_id' => RequestLog::getId()
        ]);

        ProcessHelpCrunchWebhookJob::dispatch($this->request->post(), $dataJob);

        return response()->json(['success' => true]);
    }
}
