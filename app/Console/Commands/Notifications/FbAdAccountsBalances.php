<?php

namespace App\Console\Commands\Notifications;

use App\Actions\FacebookAccountsReportAction;
use App\Facades\FbInsight;
use App\Models\Notice;
use App\Models\User;
use App\Notifications\FbAdAccountsBalance;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class FbAdAccountsBalances extends Command
{
    protected $signature = 'app:fb-ad-accounts-balances';

    protected $description = 'Command description';

    private Notice $row;

    public function handle(): void
    {
        $rows = Notice::whereType(Notice::SERVICE_FB_ADS_BALANCES)->get();

        if ($rows->isEmpty()) {
            return;
        }

        foreach ($rows as $this->row) {
            if (empty($this->row->config['accounts'])) {
                continue;
            }

            $userId = $this->row->config['user'];
            if (!$userId || !($user = User::find($userId)) || !$user->fb_access_token){
                continue;
            }
            FbInsight::setToken($user->fb_access_token);

            $data = $this->filterByResidualDays($this->getAdBalances());
//            $data = $this->getAdBalances();

            if (empty($data)) {
                continue;
            }

            $this->sendNotice($data);
        }
    }

    private const string CACHE_KEY = 'fb_ad_accounts_balances_%s_%d';

    protected function filterByResidualDays(array $data): array
    {
        return array_filter($data, static function ($item) {
            if ($item['residual_days'] > 3) {
                Cache::forget(sprintf(self::CACHE_KEY, 3, $item['residual_days']));
                Cache::forget(sprintf(self::CACHE_KEY, 1, $item['residual_days']));
            }
            if (!in_array($item['residual_days'], [1, 3], true)) {
                return false;
            }

            foreach ([3, 1] as $days) {
                $cacheKey = sprintf(self::CACHE_KEY, $days, $item['residual_days']);
                if ($item['residual_days'] !== $days){
                    continue;
                }

                if (!Cache::has($cacheKey)){
                    Cache::put($cacheKey, true, now()->addDays($days + 2));
                    return true;
                }
            }

            return false;
        });
    }

    protected function getAdBalances(): array
    {
        $accounts = array_map(static function ($account) {
            return $account['id'];
        }, $this->row->config['accounts']);

        return (new FacebookAccountsReportAction())->handle(null, null, $accounts);
    }

    protected function sendNotice($data): void
    {
        /** @var User $user */
        $user = User::find(24);
        $user->notify(new FbAdAccountsBalance($data, $this->row['name']));
    }
}
