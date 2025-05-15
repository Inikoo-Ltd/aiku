<?php

/*
 * author Arya Permana - Kirin
 * created on 14-05-2025-14h-16m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Checkout\UI;

use App\Actions\Accounting\OrderPaymentApiPoint\StoreOrderPaymentApiPoint;
use App\Actions\Accounting\PaymentAccountShop\UI\GetRetinaPaymentAccountShopData;
use App\Actions\Retina\Ecom\Basket\UI\IsOrder;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaDropshippingCheckout extends RetinaAction
{
    use IsOrder;

    public function handle(Order $order, Customer $customer): array
    {
        $orderPaymentApiPoint = StoreOrderPaymentApiPoint::run($order);

        $paymentMethods = [];

        if ($order) {
            $paymentMethods = $this->getPaymentMethods($order, $orderPaymentApiPoint);
        }

        return [
            'order'          => $order,
            'paymentMethods' => $paymentMethods,
            'balance' => $customer?->balance,
        ];
    }

    public function getPaymentMethods(Order $order, OrderPaymentApiPoint $orderPaymentApiPoint): array
    {
        $paymentMethods = [];

        $paymentMethodsData = [];

        $paymentAccountShops = $this->shop->paymentAccountShops()
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)
            ->where('show_in_checkout', true)
            ->orderby('checkout_display_position')
            ->get();
        /** @var PaymentAccountShop $paymentAccountShop */
        foreach ($paymentAccountShops as $paymentAccountShop) {
            $paymentAccountShopData = GetRetinaPaymentAccountShopData::run($order, $paymentAccountShop, $orderPaymentApiPoint);

            if ($paymentAccountShopData) {

                if ($paymentAccountShop->type == PaymentAccountTypeEnum::CHECKOUT) {
                    $paymentMethodsData[$paymentAccountShop->type->value] = $paymentAccountShop->id;
                }
                $paymentMethods[] = $paymentAccountShopData;
            }
        }

        $orderPaymentApiPoint->update(['data' => [
            'payment_methods' => $paymentMethodsData,
        ]]);

        return $paymentMethods;
    }

    public function asController(Order $order, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($order, $this->customer);
    }

    public function htmlResponse(array $checkoutData): Response
    {
        $order = Arr::get($checkoutData, 'order');

        return Inertia::render(
            'Ecom/Checkout',
            [
                'breadcrumbs'    => $this->getBreadcrumbs($order),
                'title'          => __('Basket'),
                'pageHead'       => [
                    'title' => __('Basket'),
                    'icon'  => 'fal fa-shopping-basket'
                ],
                'order'          => OrderResource::make($order)->resolve(),
                'summary'        => $order ? $this->getOrderBoxStats($order) : null,
                'paymentMethods' => Arr::get($checkoutData, 'paymentMethods'),
                'balance'        => $this->customer?->balance,
                'total_amount'   => $order->total_amount
            ]
        );
    }

    public function getBreadcrumbs(Order $order): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.dropshipping.checkout.show',
                                'parameters' => [
                                    'order' => $order->slug
                                ]
                            ],
                            'label' => __('Checkout'),
                        ]
                    ]
                ]
            );
    }
}
