<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Services\AZAnalyzeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AZController extends Controller
{
    public function __construct(Request $request, protected AZAnalyzeService  $analyzeService)
    {
        parent::__construct($request);
    }

    public function siteEvent(): array
    {
        return $this->analyzeService->handleClientEvent();
    }


    public function jsRender($id): Response
    {
        $content = $this->analyzeService->getContentJS($id);
        return (new Response($content, 200))
            ->header('Content-Type', 'application/javascript; charset=utf-8');
    }
}
