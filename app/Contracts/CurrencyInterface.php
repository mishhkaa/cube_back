<?php

namespace App\Contracts;

interface CurrencyInterface
{
    public function setDefaultCurrency(string $currency): static;

    public function getDefaultCurrency(): string;

    public function getRate(string $from, ?string $to = null, string $date = 'latest'): float;

    public function getRates(?string $currency = null, string $date = 'latest'): array;

    public function getSupportCurrencies(): array;

    public function convert(float $amount, string $from, ?string $to = null, string $date = 'latest'): float;
}
