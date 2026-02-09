<?php

namespace App\Http\Controllers;

use App\Actions\FacebookAccountsReportAction;
use App\Classes\WaitForCache;
use App\Facades\FbInsight;
use App\Models\Setting;
use App\Models\User;

class FacebookAccountsReportController extends Controller
{
    public function __invoke(WaitForCache $forCache, FacebookAccountsReportAction $action)
    {
        $from = $this->request->query('from');
        $to = $this->request->query('to');

        $accounts = $this->request->query('accounts');

        $accountsArr = $accounts ? explode(',', $accounts) : [];

        $currency = $this->request->query('currency');

        $this->setFbToken($userId = $this->request->query('user_id'));

        return $forCache->setKey("facebook-accounts-report-$userId-$from-$to-$accounts-$currency")
            ->setCallback(fn() => $action->handle($from, $to, $accountsArr, $currency))
            ->updateIfEmpty()
            ->run(300, []);
    }

    public function reportForMedianCSD(WaitForCache $forCache, FacebookAccountsReportAction $action)
    {
        $accounts = Setting::get('median_cds_customer_fb_accounts') ?: [];

        $from = $this->request->query('from');
        $to = $this->request->query('to');

        return $forCache->setKey("facebook-accounts-report-median-csd-$from-$to")
            ->setCallback(fn() => $action->handle($from, $to, $accounts))
            ->updateIfEmpty()
            ->run(600, []);
    }

    private function setFbToken(?string $userId): void
    {
        /** @var User $user */
        $user = User::find($userId === '1' ? 24 : 13);
        FbInsight::setToken($user->fb_access_token);
    }

    public function getAccounts(WaitForCache $forCache)
    {
        $this->setFbToken($userId = $this->request->query('user_id'));

        return $forCache->setKey("adsAccounts-$userId")
            ->setCallback(fn() => FbInsight::getAccounts())
            ->updateIfEmpty()
            ->run(600, []);
    }
}
