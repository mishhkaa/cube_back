<?php

namespace App\Http\Controllers\Webhooks;

use App\Facades\RequestLog;
use App\Http\Controllers\Controller;
use App\Jobs\Webhooks\ProcessAdsQuizWebhookJob;
use App\Models\DataJob;
use App\Models\Enums\JobStatus;
use Illuminate\Http\JsonResponse;

class AdsQuizController extends Controller {
    public function __invoke(): JsonResponse
    {
        $dataJob = DataJob::create([
            'queue' => 'ads-quiz-webhook',
            'status' => JobStatus::NEW,
            'request_id' => RequestLog::getId()
        ]);

        ProcessAdsQuizWebhookJob::dispatchAfterResponse($this->request->post(), $dataJob);

        return response()->json(['success' => true]);
    }
}