<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\TikTokWebEventRequest;
use App\Models\TikTokPixel;
use App\Services\Conversions\TikTokEventsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TikTokEventController extends Controller
{
    public function __construct(Request $request, protected TikTokEventsService $service)
    {
        parent::__construct($request);
    }

    public function webEvent(TikTokWebEventRequest $request, TikTokPixel $pixel): array
    {
        $externalId = $this->service->handleWebEvent($pixel, $request->validated());
        return ['id' => $externalId];
    }

    public function jsRender($id): Response
    {
        $content = $this->service->getContentJS($id);
        return (new Response($content, 200))
            ->header('Content-Type', 'application/javascript; charset=utf-8');
    }
}
