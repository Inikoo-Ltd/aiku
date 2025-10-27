<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HMRC API Configuration
    |--------------------------------------------------------------------------
    |
    | The following configuration values are used to connect to the HMRC API.
    | Make sure to set the environment variables in your .env file.
    |
    */

    'enabled' => env('HMRC_ENABLED', false),
    'client_id' => env('HMRC_CLIENT_ID', 'your-client-id'),
    'client_secret' => env('HMRC_CLIENT_SECRET', 'your-client-secret'),
    'base_url' => env('HMRC_BASE_URL', 'https://test-api.service.hmrc.gov.uk'),

];
