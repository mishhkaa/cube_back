<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'pipedrive' => [
        'token' => env('PIPEDRIVE_API_KEY'),
        'url' => env('PIPEDRIVE_API_URL')
    ],

    'currency-layer' => [
        'url' => 'http://api.currencylayer.com/',
        'keys' => env('CURRENCY_API_KEYS')
    ],

    'free-currency' => [
        'url' => 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@{date}/v1/currencies{currencyCode}.min.json',
        'fallback_url' => 'https://{date}.currency-api.pages.dev/v1/currencies{currencyCode}.min.json',
    ],

    'google-measurement-protocol' => [
        'measurement_id' => env('GOOGLE_MEASUREMENT_ID'),
        'api_secret' => env('GOOGLE_MEASUREMENT_API_SECRET'),

        'agency_measurement_id' => env('GOOGLE_MEASUREMENT_ID'),
        'agency_api_secret' => env('GOOGLE_MEASUREMENT_API_SECRET'),

        'coerandig_measurement_id' => env('COERANDIG_GOOGLE_MEASUREMENT_ID'),
        'coerandig_api_secret' => env('COERANDIG_GOOGLE_MEASUREMENT_API_SECRET'),
    ],

    'mailchimp' => [
        'apiKey' => env('MAILCHIMP_API_KEY'),
        'listId' => env('MAILCHIMP_DEFAULT_LIST_ID')
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ]
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),
    ],

    'facebook' => [
        'token' => env('FACEBOOK_API_TOKEN'),
        'token_dima' => env('FACEBOOK_API_TOKEN_DIMA'),
        'app' => [
            'id' => env('FB_APP_ID'),
            'secret' => env('FB_APP_SECRET'),
            'redirect_url' => env('FB_APP_REDIRECT_URL')
        ]
    ],

    'helpcrunch' => [
        'token' => env('HELPCRUNCH_TOKEN')
    ],

    'tiktok' => [
        'app_id' => env('TIKTOK_APP_ID'),
        'secret' => env('TIKTOK_APP_SECRET'),
    ],

    'x' => [
        'api_key' => env('X_API_KEY'),
        'api_secret' => env('X_API_SECRET'),
        'redirect_url' => env('X_REDIRECT_URL'),
    ],
    'google-ads' => [
        'json_key_file_path' => storage_path(env('GOOGLE_ADS_JSON_KEY_FILE_PATH')),
        'impersonated_email' => env('GOOGLE_ADS_IMPERSONATED_EMAIL'),
        'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
        'scopes' => 'https://www.googleapis.com/auth/adwords'
    ],

    'bsg' => [
        'api_key' => env('BSG_API_KEY'),
        'slack-users' => env('SLACK_USER_ID_FOR_BSG')
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'google_ai' => [
        'api_key' => env('GOOGLE_AI_API_KEY'),
    ],
];
