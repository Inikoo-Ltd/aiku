<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 26 Aug 2025 16:36:49 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment;

use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\OrgAction;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccountShop;
use Checkout\CheckoutApiException;
use Checkout\Payments\RefundRequest;

class RefundPaymentCheckoutCom extends OrgAction
{
    use WithCheckoutCom;

    public function handle(Payment $payment, $amount): array
    {
        return $this->refundPayment($payment, $amount);
    }

    public function refundPayment(Payment $payment,  ?float $amount = null): array
    {
        $paymentAccountShop=$payment->paymentAccountShop;
        list($publicKey, $secretKey) = $paymentAccountShop->getCredentials();

        $checkoutApi = $this->getCheckoutApi($publicKey, $secretKey);


        try {
            $refundRequest = new RefundRequest();
            if ($amount !== null) {
                if ($amount <= 0) {
                    throw new \InvalidArgumentException('Refund amount must be greater than zero');
                }

                $refundRequest->amount = $amount;
            }

            $result= $checkoutApi->getPaymentsClient()->refundPayment($payment->reference, $refundRequest);

            dd($result);


        } catch (CheckoutApiException $e) {
            dd($e);
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
}
