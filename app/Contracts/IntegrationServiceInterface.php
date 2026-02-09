<?php

namespace App\Contracts;

interface IntegrationServiceInterface
{
    public function dispatchEvent(array $data, ConversionAccountInterface $account): void;
}
