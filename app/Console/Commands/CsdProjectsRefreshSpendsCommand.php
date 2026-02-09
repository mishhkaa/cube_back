<?php

namespace App\Console\Commands;

use App\Facades\Currency;
use App\Facades\FbInsight;
use App\Facades\GoogleAds;
use App\Facades\TikTok;
use App\Models\BigQuery\CsdTable;
use App\Models\CsdProject;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class CsdProjectsRefreshSpendsCommand extends Command
{
    protected $signature = 'csd:projects-refresh-spends {--accountId=} {--date= : Examples: 2022-01-01...2022-01-22}';

    protected $description = 'Command description';

    public function handle(): void
    {
        $period = $this->getPeriod();

        [$from, $to] = [$period->getStartDate()->format('Y-m-d'), $period->getEndDate()?->format('Y-m-d')];

        $projects = $this->getProjects();

        foreach ($projects as $project) {
            $this->refreshSpends($project, $from, $to);
        }
    }

    /**
     * @return Collection<CsdProject>
     */
    private function getProjects(): Collection
    {
        return CsdProject::when($this->option('accountId'), static function (Builder $builder, $accountId) {
            return $builder->where('id', $accountId);
        })->get();
    }

    protected function getPeriod(): CarbonPeriod
    {
        if ($date = $this->option('date')) {
            $days = explode('...', $date, 2);
        } else {
            $days = [now()->subDays(3), now()->subDay()];
        }

        return CarbonPeriod::between(...$days);
    }

    private function refreshSpends(CsdProject $project, string $from, string $to): void
    {
        if ( ! $project->ad_accounts) {
            return;
        }

        $user = $project->user;

        $periods = CarbonPeriod::between($from, $to)->rangeChunks(7);

        foreach ($project->ad_accounts as $platform => $adAccounts) {
            foreach ($adAccounts as ["id" => $id, "name" => $name]) {
                foreach ($periods as $period) {
                    [$from, $to] = [$period->getStartDate()->format('Y-m-d'), $period->getEndDate()?->format('Y-m-d')];
                    $data = match ($platform) {
                        'google' => $this->getGoogleSpends($id, $from, $to),
                        'facebook' => $this->getFacebookSpends($id, $from, $to, $user),
                        'tiktok' => $this->getTiktokSpends($id, $from, $to, $user),
                    };
                    $data = array_filter($data);

                    if ( ! $data) {
                        continue;
                    }

                    CsdTable::where('name', $project->name)
                            ->where('platform', $platform)
                            ->where('ad_account_id', (string) $id)
                            ->whereIn('date', array_keys($data))
                            ->delete();

                    $insert = [];
                    foreach ($data as $date => $value) {
                        $insert[] = [
                            'name'         => $project->name,
                            'date'          => $date,
                            'platform'      => $platform,
                            'ad_account'    => $name,
                            'ad_account_id' => (string) $id,
                            'spend'        => $value,
                        ];
                    }
                    CsdTable::insert($insert);
                }
            }
        }
    }

    private function getTiktokSpends(string $accountId, string $from, string $to, $user): array
    {
        if ( ! $user->tiktok_access_token) {
            Log::info('Tiktok access token is missing for user id'.$user->id);

            return [];
        }
        try {
            TikTok::setAccessToken($user->tiktok_access_token);
            $daysSpend = TikTok::getReport($accountId, 'AUCTION_ADVERTISER', $from, $to, [], 1, ['spend', 'currency']);
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }

        $data = [];
        foreach ($daysSpend as $value) {
            $allData     = array_merge($value['metrics'], $value['dimensions']);
            $date        = explode(' ', $allData['stat_time_day'])[0];
            $data[$date] = Currency::convert($allData['spend'], $allData['currency'], 'USD', $date);
        }

        return $data;
    }

    private function getGoogleSpends(string $accountId, string $from, string $to): array
    {
        $query = 'SELECT segments.date, metrics.cost_micros, customer.currency_code FROM customer WHERE segments.date BETWEEN "'.$from.'" AND "'.$to.'"';

        try {
            $dataByDays = GoogleAds::search($query, $accountId);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $data = [];
        foreach ($dataByDays as $row) {
            $date        = $row->getSegments()?->getDate();
            $data[$date] = Currency::convert(
                round($row->getMetrics()?->getCostMicros() / 1_000_000, 2),
                $row->getCustomer()?->getCurrencyCode(),
                'USD', $date
            );
        }

        return $data;
    }

    private function getFacebookSpends(string $accountId, string $from, string $to, User $user): array
    {
        if ( ! $user->fb_access_token) {
            Log::info('Facebook access token is missing for user id'.$user->id);

            return [];
        }
        try {
            FbInsight::setToken($user->fb_access_token);
            $dataByDays = FbInsight::getInsights($accountId, $from, $to, ['spend', 'account_currency'], 'account', true);
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }

        $data = [];
        foreach ($dataByDays as $row) {
            $data[$row['date_start']] = Currency::convert($row['spend'], $row['account_currency'], 'USD', $row['date_start']);
        }

        return $data;
    }
}
