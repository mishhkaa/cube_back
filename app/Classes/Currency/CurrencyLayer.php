<?php

namespace App\Classes\Currency;

use App\Contracts\CurrencyInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CurrencyLayer implements CurrencyInterface
{
    protected PendingRequest $client;

    private const string CACHE_KEY = 'currency_keys_index';

    private string $apiKey;

    protected static array $cache = [];

    protected string $currency = 'USD';

    protected static int $retries = 0;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->setApikey();
        $this->client = Http::asJson()->baseUrl(config('services.currency-layer.url'));
    }

    /**
     * @throws Exception
     */
    protected function setApikey(): void
    {
        if (!$apiKeys = $this->getApiKeys()) {
            throw new RuntimeException('CURRENCY_API_KEYS not set in env file');
        }
        $key = Cache::get(self::CACHE_KEY, 0);
        if (empty($apiKeys[$key])) {
            $key = 0;
            Cache::forever(self::CACHE_KEY, $key);
        }
        $this->apiKey = $apiKeys[$key];
    }

    protected function getApiKeys(): array
    {
        return explode(',', config('services.currency-layer.keys', ''));
    }

    public function setDefaultCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function convert(float $amount, string $from, ?string $to = null, string $date = 'latest'): float
    {
        [$from, $to] = $this->formattedCurrency($from, $to);

        if (!$amount || $from === $to) {
            return $amount;
        }

        $rates = $this->getRates($to, $date);
        $rate = $rates[strtoupper($from)] ?? 0;

        return $rate ? ($amount / $rate) : 0;
    }

    public function getRate(string $from, ?string $to = null, string $date = 'latest'): float
    {
        [$from, $to] = $this->formattedCurrency($from, $to);

        $rates = $this->getRates($to, $date);

        return $rates[strtoupper($from)] ?? 0;
    }

    public function getRates(?string $currency = null, string $date = 'latest'): array
    {
        $currency = strtoupper($currency ?: $this->currency);
        $date = $this->formatDate($date);

        if ($rates = $this->getFromCache($currency, $date)) {
            return $rates;
        }

        return $this->send($currency, $date);
    }

    protected function formattedCurrency(string $from, ?string $to): array
    {
        return [strtoupper($from), strtoupper($to ?: $this->currency)];
    }

    protected function formatDate(string $date): string
    {
        return ($date === 'latest' ? Carbon::now() : Carbon::parse($date))
            ->format('Y-m-d');
    }

    protected function getFromCache(string $currency, string $date): ?array
    {
        if (Cache::has("currency-$currency-$date")){
            return Cache::get("currency-$currency-$date");
        }
        if (!empty(self::$cache[$date][$currency])) {
            return self::$cache[$date][$currency];
        }
        return null;
    }

    /**
     * @throws ConnectionException
     */
    protected function send(string $currency, string $date): array
    {
        $params = [
            'source' => $currency,
            'access_key' => $this->apiKey
        ];

        $rememberInCache = true;

        if ($date === date('Y-m-d')) {
            $url = 'live';
            $rememberInCache = false;
        } else {
            $params += ['date' => $date];
            $url = 'historical';
        }

        $res = $this->client->get($url, $params);

        if ($res->successful() && $res->json('success')) {
            $rates = $res->json('quotes', []);
            $formattedRates = [];
            foreach ($rates as $key => $value) {
                $formattedRates[str_replace($currency, '', $key)] = $value;
            }
            if ($rememberInCache){
                Cache::add("currency-$currency-$date", $formattedRates, Carbon::now()->addDays(30));
            }

            return self::$cache[$date][$currency] = $formattedRates;
        }


        if (in_array((int)$res->json('error.code'), [104, 106], true)) {
            $this->changeApiKey();
            self::$retries++;

            if (self::$retries < count($this->getApiKeys())) {
                return $this->send($currency, $date);
            }
        }

        Log::info('get currency failed:', $res->json());
        return [];
    }

    protected function changeApiKey(): void
    {
        $indexKey = Cache::get(self::CACHE_KEY, 0) + 1;
        $apiKeys = $this->getApiKeys();
        if (empty($apiKeys[$indexKey])) {
            $indexKey = 0;
        }
        Cache::forever(self::CACHE_KEY, $indexKey);
        $this->apiKey = $apiKeys[$indexKey];
    }

    public function getDefaultCurrency(): string
    {
        return $this->currency;
    }

    public function getSupportCurrencies(): array
    {
        return $this->client->get('list')->json('currencies', []);
    }
}
