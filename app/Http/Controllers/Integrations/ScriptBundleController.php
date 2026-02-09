<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Services\ScriptBundlesService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ScriptBundleController extends Controller
{
    public function __construct(Request $request, protected ScriptBundlesService $service)
    {
        parent::__construct($request);
    }

    public function jsRender($id): Response
    {
        $content = $this->service->getContentJS($id);
        return (new Response($content, 200))
            ->header('Content-Type', 'application/javascript; charset=utf-8');
    }
}
