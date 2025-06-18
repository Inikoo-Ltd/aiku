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
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'tiktok' => [
        'url'    => env('TIKTOK_BUSINESS_URL'),
        'base_url'    => env('TIKTOK_BASE_URL'),
        'auth_url'    => env('TIKTOK_AUTH_URL'),
        'redirect_uri' => env('TIKTOK_REDIRECT_URI'),
        'client_id' => env('TIKTOK_CLIENT_ID'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'scopes' => env('TIKTOK_SCOPES')
    ],
    'ebay' => [
        'client_id' => env('EBAY_CLIENT_ID'),
        'client_secret' => env('EBAY_CLIENT_SECRET'),
        'sandbox' => env('EBAY_SANDBOX', true),
        'redirect_uri' => env('EBAY_REDIRECT_URI')
    ],
    'amazon' => [
        'client_id' => env('AMAZON_CLIENT_ID'),
        'app_id' => env('AMAZON_APP_ID'),
        'client_secret' => env('AMAZON_CLIENT_SECRET'),
        'redirect_uri' => env('AMAZON_REDIRECT_URI', env('APP_URL') . '/oauth/amazon/callback'),
        'region' => env('AMAZON_REGION', 'eu'),
        'sandbox' => env('AMAZON_SANDBOX', true),
        'marketplace_id' => env('AMAZON_MARKETPLACE_ID'),
        'refresh_token' => env('AMAZON_REFRESH_TOKEN'),
    ],
    'apple_pay' => [
        'verification_string' => env('APPLE_PAY_VERIFICATION_STRING'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],
];
