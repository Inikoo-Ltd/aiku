<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 15:14:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentAccountShop\UI;

use App\Actions\Accounting\Traits\CalculatesPaymentWithBalance;
use App\Actions\Accounting\WithCheckoutCom;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Checkout\Payments\Product;
use Checkout\Payments\Sessions\PaymentSessionsRequest;
use Checkout\Payments\ThreeDsRequest;
use Checkout\Customers\CustomerRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRetinaPaymentAccountShopCheckoutComData
{
    use AsObject;
    use WithCheckoutCom;
    use CalculatesPaymentWithBalance;


    public function handle(Order $order, PaymentAccountShop $paymentAccountShop, OrderPaymentApiPoint $orderPaymentApiPoint): array
    {
        list($publicKey, $secretKey) = $paymentAccountShop->getCredentials();

        $checkoutApi = $this->getCheckoutApi($publicKey, $secretKey);
        if (!$checkoutApi) {
            return ['error' => __('Online payments are temporarily unavailable')];
        }

        $paymentSessionClient = $checkoutApi->getPaymentSessionsClient();

        $amountsTpBePaidDifferentPaymentAccounts = $this->calculatePaymentWithBalance(
            $order->total_amount,
            $order->customer->balance
        );

        $toPayByOther = $amountsTpBePaidDifferentPaymentAccounts['by_other'];


        $toPayByOther = (int)round((float)$toPayByOther * 100);

        $paymentSessionRequest            = new PaymentSessionsRequest();
        $paymentSessionRequest->amount    = $toPayByOther;
        $paymentSessionRequest->currency  = $order->currency->code;
        $paymentSessionRequest->reference = $order->reference;


        $product                      = new Product();
        $product->name                = 'items';
        $product->quantity            = 1;
        $product->unit_price          = $toPayByOther;
        $product->total_amount        = $toPayByOther;
        $paymentSessionRequest->items = [$product];

        $paymentSessionRequest->three_ds          = new ThreeDsRequest();
        $paymentSessionRequest->three_ds->enabled = true;

        $channelID                                    = $paymentAccountShop->getCheckoutComChannel();
        $paymentSessionRequest->processing_channel_id = $channelID;
        $paymentSessionRequest->success_url           = $this->getSuccessUrl($orderPaymentApiPoint);
        $paymentSessionRequest->failure_url           = $this->getFailureUrl($orderPaymentApiPoint);

        $paymentSessionRequest->customer       = new CustomerRequest();
        $paymentSessionRequest->customer->name = $order->customer->name;
        if ($order->customer->email) {
            $paymentSessionRequest->customer->email = $order->customer->email;
        }
        $paymentSessionRequest = $this->setCustomerPhone(
            $paymentSessionRequest,
            $order->currency->code,
            $order->customer,
            $order->billingAddress?->country?->phone_code
        );

        $paymentSessionRequest->metadata = [
            'origin'       => 'aiku',
            'operation'    => 'order',
            'api_point_id' => $orderPaymentApiPoint->id,
            'environment'  => app()->environment(),
            'server'       => config('app.server_name') ?? ''
        ];

        $paymentSessionRequest->disabled_payment_methods = [
            'bizum'
        ];


        $billingAddress = $order->billingAddress;

        $paymentSessionRequest = $this->setBillingInformation($paymentSessionRequest, $billingAddress);

        return $this->createPaymentSession($paymentSessionClient, $paymentSessionRequest);
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
