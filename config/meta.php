<?php

return [

    'base_endpoint' => env('META_BASE_ENDPOINT', 'https://graph.facebook.com'),

    'whatsapp' => [
        'access_token'    => env('WHATSAPP_ACCESS_TOKEN'),
        'api_version'     => env('WHATSAPP_API_VERSION', 'v25.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'waba_id'         => env('WHATSAPP_WABA_ID'),

        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'app_secret'           => env('WHATSAPP_APP_SECRET'),
    ],

];
