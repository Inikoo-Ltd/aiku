<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 May 2025 10:05:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting;

use Checkout\CheckoutSdk;
use Checkout\Environment;
use Sentry;

trait WithCheckoutCom
{
    public function getCheckoutApi($publicKey, $secretKey): ?\Checkout\CheckoutApi
    {

        $checkoutApi = null;
        try {
            $checkoutApi = CheckoutSdk::builder()->staticKeys()
                ->publicKey($publicKey)
                ->secretKey($secretKey)
                ->environment(app()->environment('production') ? Environment::production() : Environment::sandbox())
                ->build();
        } catch (\Exception $e) {
            Sentry::captureException($e);
        }

        return $checkoutApi;
    }

}
