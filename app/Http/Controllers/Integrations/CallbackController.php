<?php

namespace App\Http\Controllers\Integrations;

use App\Actions\IntegrationsCallback\GoogleAdsAction;
use App\Http\Controllers\Controller;
use App\Models\GoogleSheetAccount;
use App\Models\ScriptBundle;
use App\Models\TrackingUser;
use App\Services\GoogleSheetService;
use App\Services\IntegrationsService;

class CallbackController extends Controller
{
    public function handle(ScriptBundle $account): array
    {
        $user = null;
        $userId = null;
        
        if ($account->id == 31) {
            $postData = $this->request->post();
            $orderData = $postData['data'] ?? [];
            $userId = $orderData['externalIDKoristuvaca'] ?? null;
        }
        
        if ($account->id == 7) {
            $postData = $this->request->post();
            $orderData = $postData['context'] ?? $postData;
            $clientComment = $orderData['client_comment'] ?? null;
            
            if ($clientComment) {
                if (preg_match('/\["([^"]+)"\]/', $clientComment, $matches)) {
                    $externalId = $matches[1];
                    $user = TrackingUser::find($externalId);
                    if ($user) {
                        $userId = $user->id;
                    }
                }
            }
        }

        if ($account->id == 45) {
            $userId = $this->request->post('external_id') ?? $this->request->post('user_id') ?? $this->request->post('click_id') ?? $userId;
        }
        
        if (!$user && !$userId) {
            $userId = $this->request->query->get('user_id');
        }

        if (!$user && $userId) {
            $user = TrackingUser::find($userId);
        }

        if (!$user) {
            if ($account->id == 45) {
                $sheetAccount = GoogleSheetAccount::find(32);
                if ($sheetAccount) {
                    $rowForSheet = app(GoogleAdsAction::class)->buildSheet32RowForBundle45($this->request->post(), null);
                    app(GoogleSheetService::class)->handle($sheetAccount, [$rowForSheet]);
                }
                return ['success' => true];
            }
            return ['success' => false, 'message' => 'User not found'];
        }

        IntegrationsService::handleCallbackWebEvent($account, $user);

        return ['success' => true];
    }
}
