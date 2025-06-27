<?php

declare(strict_types=1);

return [
    /*
     * An override while debugging.
     */
    'url' => null,


    'grp' => [
        'enable' => env('TREBLLE_GRP_ENABLE', false),
        'api_key' => env('TREBLLE_GRP_API_KEY'),
        'project_id' => env('TREBLLE_GRP_PROJECT_ID'),
    ],
    'retina' => [
        'enable' => env('TREBLLE_RETINA_ENABLE', false),
        'api_key' => env('TREBLLE_RETINA_API_KEY'),
        'project_id' => env('TREBLLE_RETINA_PROJECT_ID'),
    ],


    /*
     * Define which environments should Treblle ignore and not monitor
     */
    'ignored_environments' => env('TREBLLE_IGNORED_ENV', 'local,dev,test,testing'),

    /*
     * Define which fields should be masked before leaving the server
     */
    'masked_fields' => [
        'password',
        'pwd',
        'secret',
        'password_confirmation',
        'cc',
        'card_number',
        'ccv',
        'ssn',
        'credit_score',
        'api_key',
    ],

    /*
     * Should be used in development mode only.
     * Enable Debug mode, will throw errors on apis.
     */
    'debug' => env('TREBLLE_DEBUG_MODE', false),
];
