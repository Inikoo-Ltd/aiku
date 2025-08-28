<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 26 Aug 2025 16:36:49 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment;

use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\OrgAction;
use App\Enums\Accounting\Payment\PaymentClassEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\Payment;
use Checkout\CheckoutApiException;
use Checkout\Payments\RefundRequest;
use Illuminate\Support\Arr;

class RefundPaymentCheckoutCom extends OrgAction
{
    use WithCheckoutCom;

    public function handle(Payment $payment, $amount): Payment
    {
        return $this->refundPayment($payment, $amount);
    }

    public function refundPayment(Payment $payment, ?float $amount = null): Payment
    {
        $paymentAccountShop = $payment->paymentAccountShop;
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

            $result = $checkoutApi->getPaymentsClient()->refundPayment($payment->reference, $refundRequest);

            $referencePayId = basename(Arr::get($result, '_links.payment.href'));

            $count = 0;
            do {
                $checkoutPayment = CheckPaymentCheckoutCom::run($payment, $referencePayId);
                sleep(1);

                match (Arr::get($checkoutPayment, 'status')) {
                    'Refunded', 'Partially Refunded' => [
                        $status = PaymentStatusEnum::SUCCESS,
                        $state = PaymentStateEnum::COMPLETED
                    ],
                    'Declined' => [
                        $status = PaymentStatusEnum::FAIL,
                        $state = PaymentStateEnum::DECLINED
                    ],
                    'Canceled', 'Expired' => [
                        $status = PaymentStatusEnum::FAIL,
                        $state = PaymentStateEnum::CANCELLED
                    ],
                    default => [
                        $status = PaymentStatusEnum::IN_PROCESS,
                        $state = PaymentStateEnum::IN_PROCESS
                    ]
                };

                if ($status === PaymentStatusEnum::SUCCESS) {
                    $count = 5;
                }

                $count++;
            } while ($count < 5);

            return StorePayment::make()->action($payment->customer, $payment->paymentAccount, [
                'type'                    => PaymentTypeEnum::REFUND,
                'original_payment_id'     => $payment->id,
                'amount'                  => -$amount,
                'payment_account_shop_id' => $payment->payment_account_shop_id,
                'status'                  => $status,
                'state'                   => $state,
                'class'                   => PaymentClassEnum::TOPUP
            ]);

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
