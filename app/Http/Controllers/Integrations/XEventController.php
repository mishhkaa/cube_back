<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\XEventRequest;
use App\Models\XPixel;
use App\Services\Conversions\XConversionsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class XEventController extends Controller
{
    public function __construct(Request $request, protected XConversionsService $service)
    {
        parent::__construct($request);
    }

    public function event(XEventRequest $request, XPixel $pixel): array
    {
        $externalId = $this->service->handleEvent($pixel, $request->validated());
        return ['id' => $externalId];
    }

    public function jsRender($id): Response
    {
        $content = $this->service->getContentJS($id);
        return (new Response($content, 200))
            ->header('Content-Type', 'application/javascript; charset=utf-8');
    }
}
