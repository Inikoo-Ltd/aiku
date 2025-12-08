<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Jun 2023 14:52:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

return [
    'host' => env('ELASTICSEARCH_HOST'),
    'api_key' => env('ELASTICSEARCH_API_KEY'),
    'ca_bundle' => env('ELASTICSEARCH_CA_BUNDLE'),
    'index_prefix' => env('ELASTICSEARCH_INDEX_PREFIX', 'aiku').'_'.env('APP_ENV', 'production').'_',
    'indices' => [
        'mappings' => [
            'universal_search' => [
                'properties' => [
                    'group_id' => [
                        'type' => 'keyword',
                    ],
                    'organisation_id' => [
                        'type' => 'keyword',
                    ],
                    'organisation_slug' => [
                        'type' => 'keyword',
                    ],
                    'shop_id' => [
                        'type' => 'keyword',
                    ],
                    'shop_slug' => [
                        'type' => 'keyword',
                    ],
                    'fulfilment_id' => [
                        'type' => 'keyword',
                    ],
                    'fulfilment_slug' => [
                        'type' => 'keyword',
                    ],
                    'website_id' => [
                        'type' => 'keyword',
                    ],
                    'website_slug' => [
                        'type' => 'keyword',
                    ],
                    'customer_id' => [
                        'type' => 'keyword',
                    ],
                    'customer_slug' => [
                        'type' => 'keyword',
                    ],
                    'sections' => [
                        'type' => 'keyword',
                    ],
                    'permissions' => [
                        'type' => 'keyword',
                    ],
                    'haystack_tier_1' => [
                        'type' => 'text',
                    ],
                    'haystack_tier_2' => [
                        'type' => 'text',
                    ],
                    'haystack_tier_3' => [
                        'type' => 'text',
                    ],
                ],
            ],
            'retina_search' => [
                'properties' => [
                    'group_id' => [
                        'type' => 'keyword',
                    ],
                    'organisation_id' => [
                        'type' => 'keyword',
                    ],
                    'website_id' => [
                        'type' => 'keyword',
                    ],
                    'customer_id' => [
                        'type' => 'keyword',
                    ],
                    'web_users' => [
                        'type' => 'keyword',
                    ],
                    'sections' => [
                        'type' => 'keyword',
                    ],
                    'permissions' => [
                        'type' => 'keyword',
                    ],
                    'haystack_tier_1' => [
                        'type' => 'text',
                    ],
                    'haystack_tier_2' => [
                        'type' => 'text',
                    ],
                    'haystack_tier_3' => [
                        'type' => 'text',
                    ],
                ],
            ],
            'iris_search' => [
                'properties' => [
                    'group_id' => [
                        'type' => 'keyword',
                    ],
                    'organisation_id' => [
                        'type' => 'keyword',
                    ],
                    'website_id' => [
                        'type' => 'keyword',
                    ],
                    'customer_id' => [
                        'type' => 'keyword',
                    ],
                    'web_users' => [
                        'type' => 'keyword',
                    ],
                    'sections' => [
                        'type' => 'keyword',
                    ],
                    'permissions' => [
                        'type' => 'keyword',
                    ],
                    'haystack_tier_1' => [
                        'type' => 'text',
                    ],
                    'haystack_tier_2' => [
                        'type' => 'text',
                    ],
                    'haystack_tier_3' => [
                        'type' => 'text',
                    ],
                ],
            ],
            'web_search' => [
                'properties' => [
                    'group_id' => [
                        'type' => 'keyword',
                    ],
                    'shop_id' => [
                        'type' => 'keyword',
                    ],
                    'organisation_id' => [
                        'type' => 'keyword',
                    ],
                    'website_id' => [
                        'type' => 'keyword',
                    ],
                    'webpage_id' => [
                        'type' => 'keyword',
                    ],
                    'image_id' => [
                        'type' => 'keyword',
                    ],
                    'sections' => [
                        'type' => 'keyword',
                    ],
                    'haystack_tier_1' => [
                        'type' => 'text',
                    ],
                    'haystack_tier_2' => [
                        'type' => 'text',
                    ],
                    'haystack_tier_3' => [
                        'type' => 'text',
                    ],
                ],
            ],
        ],
    ],
];
