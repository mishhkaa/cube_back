<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\GoogleSheetAccount;
use App\Services\GoogleSheetService;
use Illuminate\Support\Facades\Log;

class TourTechCrmOrdersController extends Controller
{
    public function __invoke(GoogleSheetService $googleSheetService)
    {
        if (!$this->request->post()){
            return;
        }

        if(!$account = GoogleSheetAccount::find(11)){
            Log::error('Google Sheets Account not found');
            return;
        }

        $googleSheetService->handle($account, [
            'created_at' => $this->request->post('paid'),
            'order_id' => $this->request->post('order_id'),
            'amount' => (int)$this->request->post('amount'),
            'origin' => $this->request->post('origin'),
            'operator' => $this->request->post('operator'),
            'project' => $this->request->post('project'),
            'utm_source' => $this->request->input('utm_source'),
            'utm_campaign' => $this->request->input('utm_campaign'),
            'utm_content' => $this->request->input('utm_content'),
            'utm_medium' => $this->request->input('utm_medium'),
            'utm_term' => $this->request->input('utm_term'),
            'name' => $this->request->input('name'),
            'qte' => $this->request->input('qte'),
            'transaction_id' => $this->request->input('transaction_id'),
            'country' => $this->request->input('country'),
        ]);
    }
}
