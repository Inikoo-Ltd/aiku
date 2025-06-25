<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Jun 2025 12:20:16 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


return [
    'cache' => [
        'website' => [
            'ttl' => env('IRIS_WEBSITE_CACHE_TTL', 3600), // 1 hour in seconds
            'prefix' => env('IRIS_WEBSITE_CACHE_PREFIX', 'iris_website_cache'),
        ],
        'webpage_path' => [
            'ttl' => env('IRIS_WEBPAGE_PATH_CACHE_TTL', 3600), // 1 hour in seconds
            'prefix' => env('IRIS_WEBPAGE_PATH_CACHE_PREFIX', 'iris_webpage_path_cache'),
        ],
        'webpage' => [
            'ttl' => env('IRIS_WEBPAGE_CACHE_TTL', 3600), // 1 hour in seconds
            'prefix' => env('IRIS_WEBPAGE_CACHE_PREFIX', 'iris_webpage_cache'),
        ]
    ],
];
