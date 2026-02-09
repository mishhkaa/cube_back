<?php

namespace App\Classes\ApiClients;

use Illuminate\Support\Facades\Http;

class XClient
{
    private const string API_ADS_URL = 'https://ads-api.x.com/';
    private const string API_BASE_OAUTH_URL = 'https://api.twitter.com/';

    private string $token;
    private string $tokenSecret;

    public function getOauthURL(string $forUserId): string
    {
        $urlParams = '?'.http_build_query(['user_id' => $forUserId]);
        $oauth = [
            'oauth_callback' => config('services.x.redirect_url').$urlParams,
        ];

        $url = self::API_BASE_OAUTH_URL.'oauth/request_token';
        $response = Http::asForm()
            ->withHeader('Authorization', $this->getOAuthHeader($url, $oauth))
            ->post($url, null);

        parse_str($response->body(), $result);
        return 'https://api.x.com/oauth/authorize?oauth_token='.$result['oauth_token'];
    }

    public function setTokenAndSecret(string $token, string $tokenSecret): static
    {
        $this->token = $token;
        $this->tokenSecret = $tokenSecret;
        return $this;
    }

    private function getDefaultOauthParams(): array
    {
        return [
            'oauth_consumer_key' => config('services.x.api_key'),
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_version' => '1.0',
        ];
    }

    public function getAccessTokenData(string $oauthToken, string $oauthVerifier): array
    {
        $oauth = [
            'oauth_token' => $oauthToken,
            'oauth_verifier' => $oauthVerifier,
        ];

        $url = self::API_BASE_OAUTH_URL.'oauth/access_token';
        $response = Http::asForm()
            ->withHeader('Authorization', $this->getOAuthHeader($url, $oauth))
            ->post($url, null);

        parse_str($response->body(), $result);
        return [$result['oauth_token'], $result['oauth_token_secret']];
    }

    private function getOAuthHeader(string $url, array $params, bool $withTokenSecret = false, string $method = 'POST'): string
    {
        if ($withTokenSecret) {
            $params['oauth_token'] = $this->token;
        }
        $params += $this->getDefaultOauthParams();
        ksort($params);
        $parameterString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $baseString = strtoupper($method).'&'.rawurlencode($url).'&'.rawurlencode($parameterString);
        $signingKey = rawurlencode(config('services.x.api_secret')).'&';
        if ($withTokenSecret) {
            $signingKey .= rawurlencode($this->tokenSecret);
        }

        $params['oauth_signature'] = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));

        $headerParams = [];
        foreach ($params as $key => $value) {
            $headerParams[] = rawurlencode($key).'="'.rawurlencode($value).'"';
        }

        return 'OAuth '.implode(', ', $headerParams);
    }

    public function sendConversion(string $pixelId, array $conversionData): \Illuminate\Http\Client\Response
    {
        $url = self::API_ADS_URL.'12/measurement/conversions/'.$pixelId;
        $authHeader = $this->getOAuthHeader($url, [], true);

        return Http::asJson()
            ->withHeader('Authorization', $authHeader)
            ->post($url, ['conversions' => [$conversionData]]);
    }

    public function getAccounts(): ?array
    {
        $url = self::API_ADS_URL.'12/accounts';
        $authHeader = $this->getOAuthHeader($url, [], true, 'GET');

        return Http::asJson()
            ->withHeader('Authorization', $authHeader)
            ->get($url)->json('data');
    }
}
