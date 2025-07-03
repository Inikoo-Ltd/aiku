<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 15:14:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentAccountShop\UI;

use App\Actions\Accounting\WithCheckoutCom;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Checkout\Payments\Sessions\PaymentSessionsRequest;
use Checkout\Payments\ThreeDsRequest;
use Lorisleiva\Actions\Concerns\AsObject;
use Sentry;

class GetRetinaPaymentAccountShopCheckoutComData
{
    use AsObject;
    use WithCheckoutCom;


    public function handle(Order $order, PaymentAccountShop $paymentAccountShop, OrderPaymentApiPoint $orderPaymentApiPoint): array
    {
        list($publicKey, $secretKey) = $paymentAccountShop->getCredentials();

        $checkoutApi = $this->getCheckoutApi($publicKey, $secretKey);

        $paymentSessionClient = $checkoutApi->getPaymentSessionsClient();

        $toPay = (float) max($order->total_amount, 0.0);
        $balance = (float) $order->customer->balance;

        $decimalPart = $toPay - floor($toPay);

        $payFloatWithBalance = min($decimalPart, $balance);

        $remainingBalance = $balance - $payFloatWithBalance;
        $payIntWithBalance = min(floor($toPay), floor($remainingBalance));

        $toPayByBalance = round($payFloatWithBalance + $payIntWithBalance, 2);
        $toPayByOther = round($toPay - $toPayByBalance, 2);

        if ($toPayByOther == 0) {
            abort(404);
        }

        $paymentSessionRequest            = new PaymentSessionsRequest();
        $paymentSessionRequest->amount    = (int)$toPayByOther * 100;
        $paymentSessionRequest->currency  = $order->currency->code;
        $paymentSessionRequest->reference = $order->reference;

        $paymentSessionRequest->three_ds          = new ThreeDsRequest();
        $paymentSessionRequest->three_ds->enabled = true;

        $channelID                                    = $paymentAccountShop->getCheckoutComChannel();
        $paymentSessionRequest->processing_channel_id = $channelID;
        $paymentSessionRequest->success_url           = $this->getSuccessUrl($orderPaymentApiPoint);
        $paymentSessionRequest->failure_url           = $this->getFailureUrl($orderPaymentApiPoint);

        $billingAddress         = $order->billingAddress;

        $paymentSessionRequest = $this->setBillingInformation($paymentSessionRequest, $billingAddress);

        try {
            $paymentSession = $paymentSessionClient->createPaymentSessions($paymentSessionRequest);
        } catch (\Exception $e) {
            $paymentSession = [
                'error' => $e->getMessage(),
            ];
            Sentry::captureException($e);
        }

        return $paymentSession;
    }


    private function getSuccessUrl(OrderPaymentApiPoint $orderPaymentApiPoint): string
    {
        return route('retina.webhooks.checkout_com.order_payment_success', $orderPaymentApiPoint->ulid);
    }

    private function getFailureUrl(OrderPaymentApiPoint $orderPaymentApiPoint): string
    {
        return route('retina.webhooks.checkout_com.order_payment_failure', $orderPaymentApiPoint->ulid);

    }

}
