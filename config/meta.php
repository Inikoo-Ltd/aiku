<?php

return [

    'base_endpoint' => env('META_BASE_ENDPOINT', 'https://graph.facebook.com'),

    'whatsapp' => [
        'api_version'     => env('WHATSAPP_API_VERSION', 'v25.0'),
        // 'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),// TODO: check and make sure how does this work
    ],

];
