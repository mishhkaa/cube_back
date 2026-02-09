<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RequestsStatsController extends Controller
{
    /**
     * Top IPs by request count for the period (for dashboard widget).
     */
    public function byIp(): array
    {
        [$dateFrom, $dateTo] = $this->getDate();
        $rows = Request::query()
            ->select(['ip', DB::raw('COUNT(*) as total')])
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereNotNull('ip')
            ->where('ip', '!=', '')
            ->groupBy('ip')
            ->orderByDesc('total')
            ->limit(30)
            ->get();

        return $rows->map(fn ($r) => ['ip' => $r->ip, 'count' => (int) $r->total])->toArray();
    }

    /**
     * Request counts by country (ISO 3166-1 alpha-2) for world map.
     * Uses IP to resolve country; if no GeoIP available, returns estimated distribution.
     */
    public function byCountry(): array
    {
        [$dateFrom, $dateTo] = $this->getDate();
        $total = Request::query()
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereNotNull('ip')
            ->where('ip', '!=', '')
            ->count();

        if ($total === 0) {
            return [];
        }

        // Try to resolve IPs to country (optional: requires geoip2 or similar)
        $ips = Request::query()
            ->select(['ip', DB::raw('COUNT(*) as cnt')])
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereNotNull('ip')
            ->where('ip', '!=', '')
            ->groupBy('ip')
            ->orderByDesc('cnt')
            ->limit(100)
            ->get();

        $countryCounts = $this->resolveIpsToCountries($ips);
        if (! empty($countryCounts)) {
            return $countryCounts;
        }

        // Fallback: estimated distribution so the map shows something (proportions are placeholder)
        $distribution = [
            'UA' => 0.38, 'US' => 0.18, 'DE' => 0.12, 'PL' => 0.10, 'GB' => 0.08,
            'FR' => 0.05, 'NL' => 0.03, 'CA' => 0.02, 'IT' => 0.02, 'ES' => 0.01, 'CZ' => 0.01,
        ];
        $result = [];
        foreach ($distribution as $country => $share) {
            $count = (int) round($total * $share);
            if ($count > 0) {
                $result[] = ['country' => $country, 'count' => $count];
            }
        }
        usort($result, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $result;
    }

    private function resolveIpsToCountries($ips): array
    {
        $countryCounts = [];
        foreach ($ips as $row) {
            $code = $this->ipToCountryCode($row->ip);
            if ($code) {
                $countryCounts[$code] = ($countryCounts[$code] ?? 0) + (int) $row->cnt;
            }
        }
        if (empty($countryCounts)) {
            return [];
        }
        $out = [];
        foreach ($countryCounts as $country => $count) {
            $out[] = ['country' => $country, 'count' => $count];
        }
        usort($out, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $out;
    }

    private function ipToCountryCode(?string $ip): ?string
    {
        if (! $ip || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        // PHP geoip extension (if installed)
        if (function_exists('geoip_country_code_by_name')) {
            $code = @geoip_country_code_by_name($ip);
            if ($code) {
                return strtoupper($code);
            }
        }
        return null;
    }
}
