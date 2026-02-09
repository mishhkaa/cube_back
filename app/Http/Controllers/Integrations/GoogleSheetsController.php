<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\GoogleSheetAccount;
use App\Services\GoogleSheetService;
use App\Services\GoogleSheetDataPreparer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GoogleSheetsController extends Controller
{
    public function __construct(
        Request $request,
        protected GoogleSheetService $googleSheetService,
        protected GoogleSheetDataPreparer $dataPreparer
    ) {
        parent::__construct($request);
    }
    public function handle(GoogleSheetAccount $account): JsonResponse
    {
        if (!$data = $this->request->post()) {
            return $this->response('no data', false);
        }

        $preparedData = $this->dataPreparer->prepare($data, $account->id);

        if (empty($preparedData)) {
            return $this->response('no filtered data', false);
        }

        $this->googleSheetService->handle($account, $preparedData);
        return $this->response();
    }


    public function jsRender($id): Response
    {
        $content = $this->googleSheetService->getContentJS($id);
        return (new Response($content, 200))
            ->header('Content-Type', 'application/javascript; charset=utf-8');
    }
}
