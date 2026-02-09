<?php

namespace App\Classes\Currency;

use App\Contracts\CurrencyInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// https://github.com/fawazahmed0/exchange-api
class FreeCurrency implements CurrencyInterface
{

    protected string $defaultCurrency = 'USD';

    private string $url;
    private string $fallbackUrl;

    public function __construct()
    {
        $this->url = config('services.free-currency.url');
        $this->fallbackUrl = config('services.free-currency.fallback_url');
    }

    public function setDefaultCurrency(string $currency): static
    {
        $this->defaultCurrency = $currency;
        return $this;
    }

    public function getDefaultCurrency(): string
    {
        return $this->defaultCurrency;
    }

    public function getRate(string $from, ?string $to = null, string $date = 'latest'): float
    {
        return $this->getRates($to, $date)[strtoupper($from)] ?? 0.0;
    }

    public function getRates(?string $currency = null, string $date = 'latest'): array
    {
        return array_change_key_case($this->send($currency ?: $this->defaultCurrency, $date), CASE_UPPER);
    }

    public function getSupportCurrencies(): array
    {
        return array_change_key_case($this->send(''), CASE_UPPER);
    }

    public function convert(float $amount, string $from, ?string $to = null, string $date = 'latest'): float
    {
        $rate = $this->getRate($from, $to, $date);
        return $rate ? ($amount / $rate) : 0;
    }

    private function send(string $currency, string $date = 'latest'): array
    {
        $currency = strtolower($currency);

        $cacheKey = "currency-$currency-$date";
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $dateFormat = ($date === 'latest' ? Carbon::now() : Carbon::parse($date))->format('Y-m-d');
        $currencyCode = $currency ? '/' . $currency : '';
        foreach ([$this->url, $this->fallbackUrl] as $url) {
            $url = str_replace(
                ['{date}', '{currencyCode}'],
                [$dateFormat, $currencyCode],
                $url
            );
            $res = Http::get($url);

            if (!$res->successful()){
                continue;
            }

            $data = $res->json($currency ?: null);
            if ($currency){
                $delay = $date === 'latest' ? Carbon::now()->addHour() : Carbon::now()->addDays(30);
                Cache::add($cacheKey, array_change_key_case($data, CASE_UPPER), $delay);
            }
            return $data;
        }
        return [];
    }
}
