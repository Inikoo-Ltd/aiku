<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 May 2025 10:05:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting;

use App\Models\Helpers\Address;
use Checkout\CheckoutSdk;
use Checkout\Environment;
use Checkout\Payments\BillingInformation;
use Checkout\Payments\Sessions\PaymentSessionsRequest;
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


    private function setBillingInformation(PaymentSessionsRequest $paymentSessionRequest, Address $billingAddress)
    {
        $address                = new \Checkout\Common\Address();
        $address->address_line1 = $billingAddress->address_line_1;
        $address->address_line2 = $billingAddress->address_line_2;

        $address->city    = $billingAddress->locality;
        $address->state   = $billingAddress->administrative_area;
        $address->zip     = $billingAddress->postal_code;
        $address->country = $billingAddress->country->code;

        $paymentSessionRequest->billing          = new BillingInformation();
        $paymentSessionRequest->billing->address = $address;

        return $paymentSessionRequest;
    }


}
