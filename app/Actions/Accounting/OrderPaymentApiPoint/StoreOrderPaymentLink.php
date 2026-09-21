<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrderPaymentApiPoint;

use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Checkout\Payments\Links\PaymentLinkRequest;
use Checkout\Customers\CustomerRequest;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Sentry;

/**
 * A link staff hand to the customer to pay what an order still owes. It is bound to an api point
 * the same way the checkout page is, so the capture webhook records the payment against the order
 * through the one success path instead of somebody matching it by hand.
 */
class StoreOrderPaymentLink extends OrgAction
{
    use WithOrderingEditAuthorisation;
    use WithCheckoutCom;

    public const int EXPIRES_IN_SECONDS = 604800;

    public static function amountDue(Order $order): float
    {
        return round((float)$order->total_amount - (float)$order->payment_amount, 2);
    }

    public static function checkoutPaymentAccountShop(Order $order): ?PaymentAccountShop
    {
        return $order->shop->paymentAccountShops()
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)
            ->where('type', PaymentAccountTypeEnum::CHECKOUT)
            ->first();
    }

    public function handle(Order $order): OrderPaymentApiPoint
    {
        if ($order->state == OrderStateEnum::CANCELLED) {
            abort(422, __('This order is cancelled'));
        }

        $amountDue = self::amountDue($order);
        if ($amountDue <= 0) {
            abort(422, __('This order has nothing left to pay'));
        }

        $paymentAccountShop = self::checkoutPaymentAccountShop($order);
        if (!$paymentAccountShop) {
            abort(422, __('This shop does not take card payments'));
        }

        $orderPaymentApiPoint = StoreOrderPaymentApiPoint::run($order);
        $orderPaymentApiPoint = UpdateOrderPaymentApiPoint::run($orderPaymentApiPoint, [
            'data' => ['payment_methods' => [PaymentAccountTypeEnum::CHECKOUT->value => $paymentAccountShop->id]]
        ]);

        $paymentLink = $this->createCheckoutComPaymentLink(
            $paymentAccountShop,
            $this->paymentLinkRequest($order, $paymentAccountShop, $orderPaymentApiPoint, $amountDue)
        );

        $url = Arr::get($paymentLink, '_links.redirect.href');
        if (!$url) {
            abort(422, __('The payment link could not be created, try again in a moment'));
        }

        return UpdateOrderPaymentApiPoint::run($orderPaymentApiPoint, [
            'data' => [
                'payment_link' => [
                    'id'         => Arr::get($paymentLink, 'id'),
                    'url'        => $url,
                    'amount'     => $amountDue,
                    'expires_on' => Arr::get($paymentLink, 'expires_on'),
                ]
            ]
        ]);
    }

    protected function paymentLinkRequest(Order $order, PaymentAccountShop $paymentAccountShop, OrderPaymentApiPoint $orderPaymentApiPoint, float $amountDue): PaymentLinkRequest
    {
        $paymentLinkRequest                        = new PaymentLinkRequest();
        $paymentLinkRequest->amount                = (int)round($amountDue * 100);
        $paymentLinkRequest->currency              = $order->currency->code;
        $paymentLinkRequest->reference             = $order->reference;
        $paymentLinkRequest->description           = __('Order :reference', ['reference' => $order->reference]);
        $paymentLinkRequest->expires_in            = self::EXPIRES_IN_SECONDS;
        $paymentLinkRequest->processing_channel_id = $paymentAccountShop->getCheckoutComChannel();

        $paymentLinkRequest->customer       = new CustomerRequest();
        $paymentLinkRequest->customer->name = $order->customer->name;
        if ($order->customer->email) {
            $paymentLinkRequest->customer->email = $order->customer->email;
        }

        $paymentLinkRequest->metadata = [
            'origin'       => 'aiku',
            'operation'    => 'order',
            'api_point_id' => $orderPaymentApiPoint->id,
            'environment'  => app()->environment(),
            'server'       => config('app.server_name') ?? ''
        ];

        $paymentLinkRequest->disabled_payment_methods = ['bizum'];

        if ($order->shop->website?->domain) {
            $paymentLinkRequest->return_url = 'https://'.$order->shop->website->domain;
        }

        return $this->setBillingInformation($paymentLinkRequest, $order->billingAddress);
    }

    /**
     * @return array<string, mixed>
     */
    public function createCheckoutComPaymentLink(PaymentAccountShop $paymentAccountShop, PaymentLinkRequest $paymentLinkRequest): array
    {
        list($publicKey, $secretKey) = $paymentAccountShop->getCredentials();

        $checkoutApi = $this->getCheckoutApi($publicKey, $secretKey);
        if (!$checkoutApi) {
            return [];
        }

        try {
            return $checkoutApi->getPaymentLinksClient()->createPaymentLink($paymentLinkRequest);
        } catch (\Exception $e) {
            Sentry::captureException($e);

            return [];
        }
    }

    /**
     * @return array{url: string, amount: float, currency: string, expires_on: ?string}
     */
    public function jsonResponse(OrderPaymentApiPoint $orderPaymentApiPoint): array
    {
        return [
            'url'        => Arr::get($orderPaymentApiPoint->data, 'payment_link.url'),
            'amount'     => Arr::get($orderPaymentApiPoint->data, 'payment_link.amount'),
            'currency'   => $orderPaymentApiPoint->order->currency->code,
            'expires_on' => Arr::get($orderPaymentApiPoint->data, 'payment_link.expires_on'),
        ];
    }

    public function asController(Order $order, ActionRequest $request): OrderPaymentApiPoint
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order);
    }

    public function action(Order $order): OrderPaymentApiPoint
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order);
    }
}
