<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Aug 2023 12:51:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

return [
    'base_url' => env('IMGPROXY_URL'),

    // security
    'key' => env('IMGPROXY_KEY'),
    'salt' => env('IMGPROXY_SALT'),
    'signature_size' => env('IMGPROXY_SIGNATURE_SIZE'),

    // possible values (just for reference)
    'resize' => 'fit',
    'width' => 640,
    'height' => 360,
    'gravity' => 'no',
    /**
     * If set to 0, imgproxy will not enlarge the image
     * if it is smaller than the given size.
     * With any other value, imgproxy will enlarge the image.
     */
    'enlarge' => 0,
    'extension' => 'png',

    // limitations
    'resize_values' => ['fit', 'fill', 'crop'],
    'gravity_values' => [
        'no', // north (top edge)
        'so', // south (bottom edge)
        'ea', // east (right edge)
        'we', // west (left edge)
        'ce', // center
        'sm', // smart
    ],
    /**
     * MaxSrcDimension for width or height in pixels
     */
    'max_dim_px' => 8192,
    /**
     * The supported formats
     */
    'formats' => explode(',', env('IMGPROXY_FORMATS', 'jpeg,jpg,png,gif,webp,avif')),
    'default_extension' => false,
];
