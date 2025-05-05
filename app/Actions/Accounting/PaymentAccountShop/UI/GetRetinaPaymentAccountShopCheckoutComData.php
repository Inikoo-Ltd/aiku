<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 15:14:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentAccountShop\UI;

use App\Actions\Accounting\OrderPaymentApiPoint\StoreOrderPaymentApiPoint;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Checkout\CheckoutSdk;
use Checkout\Common\Address;
use Checkout\Environment;
use Checkout\Payments\BillingInformation;
use Checkout\Payments\Sessions\PaymentSessionsRequest;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;
use Sentry;

class GetRetinaPaymentAccountShopCheckoutComData
{
    use AsObject;


    public function handle(Order $order, PaymentAccountShop $paymentAccountShop): array
    {
        list($publicKey, $secretKey, $channelID) = $this->getCredentials($paymentAccountShop);
        $checkoutApi = null;
        try {
            $checkoutApi = CheckoutSdk::builder()->staticKeys()
                ->publicKey($publicKey) // optional, only required for operations related with tokens
                ->secretKey($secretKey)
                ->environment(app()->environment('production') ? Environment::production() : Environment::sandbox())
                ->build();
        } catch (\Exception $e) {
            Sentry::captureException($e);
        }

        $paymentSessionClient = $checkoutApi->getPaymentSessionsClient();

        $orderPaymentApiPoint             = StoreOrderPaymentApiPoint::run($order, $paymentAccountShop);
        $paymentSessionRequest            = new PaymentSessionsRequest();
        $paymentSessionRequest->amount    = (int)$order->total_amount * 100;
        $paymentSessionRequest->currency  = $order->currency->code;
        $paymentSessionRequest->reference = $order->reference;

        $paymentSessionRequest->processing_channel_id = $channelID;
        $paymentSessionRequest->success_url           = $this->getSuccessUrl($orderPaymentApiPoint);
        $paymentSessionRequest->failure_url           = $this->getFailureUrl($orderPaymentApiPoint);

        $billingAddress         = $order->billingAddress;
        $address                = new Address();
        $address->address_line1 = $billingAddress->address_line_1;
        $address->address_line2 = $billingAddress->address_line_2;

        $address->city    = $billingAddress->locality;
        $address->state   = $billingAddress->administrative_area;
        $address->zip     = $billingAddress->postal_code;
        $address->country = $billingAddress->country->code;

        $paymentSessionRequest->billing          = new BillingInformation();
        $paymentSessionRequest->billing->address = $address;




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

    public function getCredentials(PaymentAccountShop $paymentAccountShop): array
    {
        if (app()->environment('production')) {
            return [
                Arr::get($paymentAccountShop->paymentAccount->data, 'credentials.public_key'),
                Arr::get($paymentAccountShop->paymentAccount->data, 'credentials.secret_key'),
                Arr::get($paymentAccountShop->data, 'credentials.payment_channel'),
            ];
        } else {
            return [
                config('app.sandbox.checkout_com.public_key'),
                config('app.sandbox.checkout_com.secret_key'),
                config('app.sandbox.checkout_com.payment_channel'),
            ];
        }
    }

    private function getSuccessUrl(OrderPaymentApiPoint $orderPaymentApiPoint): string
    {
        if (app()->environment('local')) {
            return config('app.sandbox.share_url').'/webhooks/payment-success/'.$orderPaymentApiPoint->ulid;
        } else {
            return route('webhooks.payment_success', $orderPaymentApiPoint->ulid);
        }
    }

    private function getFailureUrl(OrderPaymentApiPoint $orderPaymentApiPoint): string
    {
        if (app()->environment('local')) {
            return config('app.sandbox.share_url').'/webhooks/payment-failure/'.$orderPaymentApiPoint->ulid;
        } else {
            return route('webhooks.payment_failure', $orderPaymentApiPoint->ulid);
        }
    }

}
