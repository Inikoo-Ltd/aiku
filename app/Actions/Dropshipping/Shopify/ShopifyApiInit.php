<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify;

use Gnikyt\BasicShopifyAPI\BasicShopifyAPI;
use Gnikyt\BasicShopifyAPI\Options;

/**
 * The library builds its Guzzle client in the constructor, so Guzzle options set on the API afterwards never
 * reach a request. This is shopify-app.api_init, which runs before that.
 *
 * A Shopify call may take up to 60 s once connected (delivery profile writes take over 10 s on some stores),
 * and is never retried by the library: mutations are not idempotent, and ShopifyThrottleRetryMiddleware
 * already retries 429 and GraphQL THROTTLED answers.
 */
class ShopifyApiInit
{
    public const array GUZZLE_OPTIONS = [
        'timeout'            => 60.0,
        'connect_timeout'    => 10.0,
        'max_retry_attempts' => 0,
    ];

    public static function make(Options $options): BasicShopifyAPI
    {
        $options->setGuzzleOptions(self::GUZZLE_OPTIONS);

        $timeStore  = config('shopify-app.api_time_store');
        $limitStore = config('shopify-app.api_limit_store');
        $deferrer   = config('shopify-app.api_deferrer');

        return new BasicShopifyAPI($options, new $timeStore(), new $limitStore(), new $deferrer());
    }
}
