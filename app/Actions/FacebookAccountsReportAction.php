<?php

namespace App\Actions;

use App\Classes\PoolInherit;
use App\Facades\Currency;
use App\Facades\FbInsight;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class FacebookAccountsReportAction
{

    private const array EXCLUDES_ACCOUNTS = [
        '1880659952181266',
        '3178008552519443',
        '426082511267056',
        '1244747335918116',
        '366890751475261',
        '1672007209603200',
        '1672007209603200',
        '1672007209603200',
        '1229860480503098'
    ];

    private const array ACCOUNT_STATUSES = [
        1 => 'ACTIVE',
        2 => 'DISABLED',
        3 => 'UNSETTLED',
        7 => 'PENDING_RISK_REVIEW',
        8 => 'PENDING_SETTLEMENT',
        9 => 'IN_GRACE_PERIOD',
        100 => 'PENDING_CLOSURE',
        101 => 'CLOSED',
        201 => 'ANY_ACTIVE',
        202 => 'ANY_CLOSED',
    ];

    public function handle(?string $fromDate, ?string $toDate, array $accounts = [], ?string $currency = 'USD'): array
    {
        $accounts = $accounts ?: $this->getAccounts();

        $params = $this->getParams();

        $responses = FbInsight::pool(static function (PoolInherit $pool) use ($fromDate, $toDate, $params, $accounts) {
            foreach ($accounts as $account) {
                $account = FbInsight::formattedId($account);
                $pool->as($account)
                    ->get("$account/insights/", $params + ['fields' => 'spend']);

                $pool->as($account.'_info')
                    ->get($account, ['fields' => 'balance,currency,name,spend_cap,amount_spent,account_status']);

                if ($fromDate && $toDate) {
                    $pool->as($account.'_spend')
                        ->get("/$account/insights/", [
                            'fields' => 'spend',
                            'level' => 'account',
                            'time_range' => [
                                'since' => $fromDate,
                                'until' => $toDate
                            ]
                        ]);
                }
            }
        });

        $data = [];

        foreach ($accounts as $account) {
            $account = FbInsight::formattedId($account);
            $accountInfo = $responses[$account.'_info']->json();
            if (empty($accountInfo) || !empty($accountInfo['error'])) {
                $accountInfo = [
                    'balance' => 0,
                    'currency' => 'USD',
                    'name' => $account,
                    'spend_cap' => 0,
                    'amount_spent' => 0,
                    'account_status' => 'DISCONNECTED',
                    'id' => $account
                ];
            }

            try {
                Currency::setDefaultCurrency($currency ?: $accountInfo['currency']);
            }catch (Throwable $exception){
                Log::info('currency var: ', ['currency' => $accountInfo['currency'], 'accountInfo' => $accountInfo]);
                report($exception);
            }

            $accountSpends = $responses[$account]->json('data');
            $accountSpendForDates = $responses[$account.'_spend']['data'] ?? [];

            $isBudget30Days = 0;
            if ($accountSpends) {
                unset($accountSpends[0]);
                $isBudget30Days = 1;
            }

            $sumSpend = $accountSpends
                ? Currency::convert(array_sum(array_column($accountSpends, 'spend')), $accountInfo['currency'])
                : 0;
            $spendDaily = $accountSpends ? round($sumSpend / count($accountSpends), 2) : 0;
            $amountResidual = round($accountInfo['spend_cap'] - $accountInfo['amount_spent'], 2);

            $data[] = [
                    'company' => $this->getCompany($accountInfo['name']),
                    'is_budget_30_days' => $isBudget30Days,
                    'account_status' => self::ACCOUNT_STATUSES[$accountInfo['account_status']]
                        ?? $accountInfo['account_status'],
                    'id' => $account,
                    'balance' => !empty($accountInfo['balance'])
                        ? Currency::convert($accountInfo['balance'] / 100, $accountInfo['currency'])
                        : 0,
                    'amount_spent' => Currency::convert($accountInfo['amount_spent'] / 100, $accountInfo['currency']),
                    'spend_cap' => Currency::convert($accountInfo['spend_cap'] / 100, $accountInfo['currency']),
                    'spend_daily' => $spendDaily,
                    'spend_weekly' => $sumSpend,
                    'amount_residual' => $amountResidual > 0 ? $amountResidual / 100 : 0,
                    'spend' => $accountSpendForDates
                        ? Currency::convert($accountSpendForDates[0]['spend'] ?? 0, $accountInfo['currency'])
                        : 0,
                    'residual_days' => $spendDaily && $amountResidual > 0
                        ? floor(($amountResidual / 100) / $spendDaily)
                        : 0
                ] + $accountInfo;
        }

        return $data;
    }

    protected function getCompany($name): string
    {
        if (str_starts_with($name, 'si_Kompetentnost')) {
            return 'Httpool';
        }
        if (str_starts_with($name, 'TS_')) {
            return 'Amnet';
        }
        return '';
    }

    protected function getParams(): array
    {
        $params = [
            'time_ranges' => [],
            'level' => 'account'
        ];
        $params['time_ranges'][] = [
            'since' => Carbon::now()->subDays(31)->format('Y-m-d'),
            'until' => Carbon::now()->subDay()->format('Y-m-d')
        ];
        for ($i = 1; $i < 6; $i++) {
            $day = Carbon::now()->subDays($i)->format('Y-m-d');
            $params['time_ranges'][] = [
                'since' => $day,
                'until' => $day
            ];
        }

        return $params;
    }

    protected function getAccounts(): array
    {
        $idsFacebookAccounts = array_column(FbInsight::getAccounts(['id', 'name']), 'id');

        foreach ($idsFacebookAccounts as $key => $id) {
            if (in_array((string)str_replace('act_', '', $id), self::EXCLUDES_ACCOUNTS, true)) {
                unset($idsFacebookAccounts[$key]);
            }
        }

        return $idsFacebookAccounts;
    }
}
