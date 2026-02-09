<?php

namespace App\Console\Commands;

use App\Actions\FacebookAccountsReportAction;
use App\Facades\FbInsight;
use App\Models\CsdProject;
use App\Models\GoogleSheetAccount;
use App\Models\Notice;
use App\Models\User;
use App\Services\GoogleSheetService;
use Illuminate\Console\Command;

class AddAdsBalancesToGoogleSheetCommand extends Command
{
    protected $signature = 'add:ads-balances-to-google-sheet';

    protected $description = 'Command description';

    private CsdProject $row;

    public function handle(): void
    {
        $rows = CsdProject::all();

        if ($rows->isEmpty()) {
            return;
        }

        foreach ($rows as $this->row) {
            if (empty($this->row->ad_accounts['facebook'])) {
                continue;
            }

            $userId = $this->row->user_id;
            if (!$userId || !($user = User::find($userId)) || !$user->fb_access_token){
                continue;
            }
            FbInsight::setToken($user->fb_access_token);

            $data = $this->getAdBalances();

            if (empty($data)) {
                continue;
            }

            $this->addDataToGoogleSheet($data);
        }
    }

    protected function getAdBalances(): array
    {
        $accounts = array_map(static function ($account) {
            return $account['id'];
        }, $this->row->ad_accounts['facebook']);

        return (new FacebookAccountsReportAction())->handle(null, null, $accounts);
    }

    private function addDataToGoogleSheet(array $data): void
    {
        static $account;

        if (empty($account)){
            $account = GoogleSheetAccount::find(18);
        }

        $data = array_map(function ($item) {
            return [
                'project' => $this->row['name'],
                'name' => $item['name'],
                'balance' => $item['amount_residual'] . $item['currency'],
                'daily' => $item['spend_daily'] . $item['currency'],
                'end_days' => $item['residual_days'] . 'д.',
                'weekly' => round($item['spend_daily'] * 7, 2) . $item['currency'],
            ];
        }, $data);

        (new GoogleSheetService())->handle($account, $data);
    }
}
