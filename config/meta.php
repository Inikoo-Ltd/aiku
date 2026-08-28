<?php

return [

    'base_endpoint' => env('META_BASE_ENDPOINT', 'https://graph.facebook.com'),

    'whatsapp' => [
        'api_version' => env('WHATSAPP_API_VERSION', 'v25.0'),

        // The number, the WABA and the Meta app all differ per shop and organisation, so
        // they live in their settings. Only the webhook verify token stays here: Meta's
        // verification request carries nothing that identifies which organisation it is
        // for, so there is nothing to look one up by.
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
    ],

];
