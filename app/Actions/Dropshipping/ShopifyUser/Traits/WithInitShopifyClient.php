<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\ShopifyUser\Traits;

use Gnikyt\BasicShopifyAPI\Contracts\RestRequester;
use Sentry;

trait WithInitShopifyClient
{
    public function getShopifyClient(): RestRequester|null
    {
        try {
            $api = $this->api();
            $api->getOptions()->setGuzzleOptions(['timeout' => 90.0, 'max_retry_attempts' => 0,
                'default_retry_multiplier' => 0.0,]);

            return $api->getRestClient();
        } catch (\Exception $e) {
            Sentry::captureMessage($e->getMessage());

            return null;
        }
    }
}
