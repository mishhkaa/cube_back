<?php

namespace App\Http\Controllers\Webhooks;

use App\Facades\RequestLog;
use App\Http\Controllers\Controller;
use App\Jobs\Webhooks\ProcessSlackWebhookJob;
use App\Models\DataJob;
use App\Models\Enums\JobStatus;
use Illuminate\Http\JsonResponse;

class SlackController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $dataJob = DataJob::create([
            'queue' => 'slack-webhook',
            'status' => JobStatus::NEW,
            'request_id' => RequestLog::getId()
        ]);

        $data = json_decode($this->request->post('payload', ''), true);
        ProcessSlackWebhookJob::dispatch($data, $dataJob);

        return response()->json(['success' => true]);
    }
}
