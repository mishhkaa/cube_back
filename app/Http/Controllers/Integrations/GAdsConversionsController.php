<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\GAdsConversionsEventRequest;
use App\Models\GoogleAdsAccount;
use App\Services\Conversions\GoogleOfflineConversionsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GAdsConversionsController extends Controller
{
    public function __construct(Request $request, protected GoogleOfflineConversionsService $service)
    {
        parent::__construct($request);
    }

    public function event(GoogleAdsAccount $gadsAccount, GAdsConversionsEventRequest $request): array
    {
        $clickId = $this->service->handleEvent($gadsAccount, $request->validated());

        return ['id' => $clickId];
    }

    public function conversions(GoogleAdsAccount $gadsAccount): Response
    {
        $isGoogle = str_contains($this->request->userAgent() ?: '', 'google-xrawler');
        $content = $this->service->getCSVConversions($gadsAccount, $isGoogle);

        return (new Response($content))
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-disposition', 'attachment;filename=conversions.csv');
    }

    public function jsRender(int $id): Response
    {
        $content = $this->service->getContentJS($id);
        return (new Response($content, 200))
            ->header('Content-Type', 'application/javascript; charset=utf-8');
    }
}
