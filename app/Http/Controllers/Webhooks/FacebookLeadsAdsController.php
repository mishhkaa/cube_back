<?php

namespace App\Http\Controllers\Webhooks;

use App\Facades\RequestLog;
use App\Http\Controllers\Controller;
use App\Jobs\FacebookLeadAdsJob;
use App\Models\DataJob;
use App\Models\Enums\JobStatus;
use Illuminate\Http\JsonResponse;

class FacebookLeadsAdsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $dataJob = DataJob::create([
            'queue' => 'facebook-lead-ads-webhook',
            'status' => JobStatus::NEW,
            'request_id' => RequestLog::getId()
        ]);

        FacebookLeadAdsJob::dispatchAfterResponse($this->request->post(), $dataJob);

        return response()->json(['success' => true]);
    }
}
