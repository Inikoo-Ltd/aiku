<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 May 2025 10:05:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting;

use App\Models\Accounting\PaymentAccountShop;
use App\Models\Helpers\Address;
use Checkout\CheckoutApiException;
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


    private function setBillingInformation(PaymentSessionsRequest $paymentSessionRequest, Address $billingAddress): PaymentSessionsRequest
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

    private function getCheckOutPayment(PaymentAccountShop $paymentAccountShop, string $paymentID): array
    {
        list($publicKey, $secretKey) = $paymentAccountShop->getCredentials();


        $checkoutApi = $this->getCheckoutApi($publicKey, $secretKey);

        try {
            return $checkoutApi->getPaymentsClient()->getPaymentDetails($paymentID);
        } catch (CheckoutApiException $e) {
            \Sentry\captureException($e);
            $error_details    = $e->error_details;
            $http_status_code = isset($e->http_metadata) ? $e->http_metadata->getStatusCode() : null;

            return [
                'error' => true,
                'message' => $error_details,
                'http_status_code' => $http_status_code
            ];
        }

    }

    protected function getFailureTitle($status): string
    {
        $title = __('Error');
        if ($status == 'Declined') {
            $title = __('Declined');
        } elseif ($status == 'Expired') {
            $title = __('Expired');
        } elseif ($status == 'Canceled') {
            $title = __('Canceled');
        }

        return $title;
    }

    protected function getFailureMessage($status): string
    {
        $message = __('There was an error processing your card. Please try again or use a different payment method.');

        if ($status == 'Declined') {
            $message = __('Your card was declined by the issuing bank. Please try another payment method or contact your bank for more information.');
        } elseif ($status == 'Expired') {
            $message = __('Your payment session has expired. Please try the transaction again.');
        } elseif ($status == 'Canceled') {
            $message = __('This payment was canceled. Please try again when you are ready to complete your payment.');
        } elseif ($status == 'Failed') {
            $message = __('The payment processing failed. Please verify your card details and try again.');
        }

        return $message;
    }

}
