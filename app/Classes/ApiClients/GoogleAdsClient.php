<?php

namespace App\Classes\ApiClients;

use Generator;
use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Lib\V20\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\V20\Services\ListAccessibleCustomersRequest;
use Google\Ads\GoogleAds\V20\Services\SearchGoogleAdsRequest;
use Throwable;

class GoogleAdsClient
{

    protected \Google\Ads\GoogleAds\Lib\V20\GoogleAdsClient $googleAdsClient;

    public function __construct()
    {
        try {
            $oAuth2Credential      = (new OAuth2TokenBuilder())
                ->withJsonKeyFilePath(config('services.google-ads.json_key_file_path'))
                ->withImpersonatedEmail(config('services.google-ads.impersonated_email'))
                ->withScopes(config('services.google-ads.scopes'))
                ->build();
            $this->googleAdsClient = (new GoogleAdsClientBuilder())
                ->withOAuth2Credential($oAuth2Credential)
                ->withDeveloperToken(config('services.google-ads.developer_token'))
                ->build();
        }catch (Throwable $e){
            report($e);
            throw $e;
        }
    }

    public function getCustomersIds(): array
    {
        $customerServiceClient       = $this->googleAdsClient->getCustomerServiceClient();
        $accessibleCustomersResponse = $customerServiceClient->listAccessibleCustomers(
            new ListAccessibleCustomersRequest()
        );
        $resourceNames               = $accessibleCustomersResponse->getResourceNames();
        $customers                   = [];
        foreach ($resourceNames as $resourceName) {
            $customers[] = explode('/', $resourceName)[1];
        }

        return $customers;
    }

    /**
     * @param  array  $fields
     *
     * @return array<int, \Google\Ads\GoogleAds\V20\Services\GoogleAdsRow>
     */
    public function getCustomers(array $fields, array $customersIds = []): array
    {
        $customersIds = $customersIds ?: $this->getCustomersIds();

        $data  = [];
        $query = "SELECT ".implode(', ', $fields)." FROM customer";
        foreach ($customersIds as $customerId) {
            $data[$customerId] = $this->search($query, $customerId)->current();
        }

        return $data;
    }

    /**
     * @param  string  $query
     * @param  int  $customerId
     *
     * @return Generator<\Google\Ads\GoogleAds\V20\Services\GoogleAdsRow>
     */
    public function search(string $query, int $customerId): Generator
    {
        $googleAdsServiceClient = $this->googleAdsClient->getGoogleAdsServiceClient();
        $searchResponse         = $googleAdsServiceClient->search(
            (new SearchGoogleAdsRequest())->setQuery($query)->setCustomerId($customerId),
        );

        return $searchResponse->iterateAllElements();
    }
}
