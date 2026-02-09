<?php

namespace App\Http\Controllers\Api;

use App\Classes\WaitForCache;
use App\Facades\GoogleAds;
use Google\Ads\GoogleAds\V20\Enums\CustomerStatusEnum\CustomerStatus;

class GoogleAdsController
{
    public function adsAccounts(WaitForCache $forCache): array
    {
        try {
            return $forCache
                ->setKey("google-ads-accounts")
                ->setCallback(function () {
                    $data = GoogleAds::getCustomers([
                        'customer.descriptive_name', 'customer.id', 'customer.status'
                    ]);

                    $customers = [];
                    foreach ($data as $customerId => $customer) {
                        $customers[] = [
                            'id' => $customerId,
                            'name' => $customer->getCustomer()?->getDescriptiveName(),
                            'status' => CustomerStatus::name($customer->getCustomer()?->getStatus()),
                        ];
                    }
                    return $customers;
                })
                ->updateIfEmpty()
                ->run(600, []);
        } catch (\Exception $e) {
            abort(400, $e->getMessage());
        }
    }
}
