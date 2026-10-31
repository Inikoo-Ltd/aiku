<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Mar 2025 14:38:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configures if and how Inertia uses Server Side Rendering
    | to pre-render the initial visits made to your application's pages.
    |
    | Do note that enabling these options will NOT automatically make SSR work,
    | as a separate rendering service needs to be available. To learn more,
    | please visit https://inertiajs.com/server-side-rendering
    |
    */

    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', true),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
        'bundle' => base_path('bootstrap/ssr/ssr-iris.mjs'),
    ],

    'pages' => [

        'paths' => [

            resource_path('js/Pages'),

        ],

        'extensions' => [

            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',

        ],

    ],

    'testing' => [

        'ensure_pages_exist' => true,

    ],

    'history' => [

        'encrypt' => (bool) env('INERTIA_ENCRYPT_HISTORY', false),

    ],

];
