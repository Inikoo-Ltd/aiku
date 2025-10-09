<?php

/*
 * author Arya Permana - Kirin
 * created on 14-05-2025-14h-16m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Checkout\UI;

use App\Actions\Accounting\OrderPaymentApiPoint\StoreOrderPaymentApiPoint;
use App\Actions\Accounting\Traits\CalculatesPaymentWithBalance;
use App\Actions\Retina\Dropshipping\Orders\ShowRetinaDropshippingBasket;
use App\Actions\Retina\Ecom\Basket\UI\IsOrder;
use App\Actions\Retina\GetRetinaPaymentMethods;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\Sales\OrderResource;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaDropshippingCheckout extends RetinaAction
{
    use IsOrder;
    use CalculatesPaymentWithBalance;

    public function handle(Order $order, Customer $customer): array
    {
        $orderPaymentApiPoint = StoreOrderPaymentApiPoint::run($order);


        $paymentMethods = GetRetinaPaymentMethods::run($order, $orderPaymentApiPoint);


        return [
            'order'          => $order,
            'paymentMethods' => $paymentMethods,
            'balance'        => $customer->balance,
        ];
    }


    public function asController(Order $order, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($order, $this->customer);
    }

    public function htmlResponse(array $checkoutData): Response
    {
        /** @var Order $order */
        $order = Arr::get($checkoutData, 'order');


        $paymentAmounts = $this->calculatePaymentWithBalance(
            $order->total_amount,
            $this->customer->balance
        );

        $toPay = $paymentAmounts['total'];
        $toPayByBalance = $paymentAmounts['by_balance'];
        $toPayByOther = $paymentAmounts['by_other'];



        return Inertia::render(
            'Dropshipping/RetinaDropshippingCheckout',
            [
                'breadcrumbs'    => $this->getBreadcrumbs($order),
                'title'          => __('Checkout'),
                'pageHead'    => [
                    'title'      => $order->reference,
                    'model'      => __('Checkout'),
                ],
                'order'          => OrderResource::make($order)->resolve(),
                'box_stats'      => ShowRetinaDropshippingBasket::make()->getDropshippingBasketBoxStats($order),
                'paymentMethods' => Arr::get($checkoutData, 'paymentMethods'),
                'balance'        => $this->customer->balance,
                'total_amount'   => $order->total_amount,
                'currency_code'  => $order->currency->code,
                'to_pay_data'    => [
                    'total'      => $toPay,
                    'by_balance' => $toPayByBalance,
                    'by_other'   => $toPayByOther

                ],
                'routes'         => [
                    'pay_with_balance' => [
                        'name'       => 'retina.models.order.pay_with_balance',
                        'parameters' => [
                            'order' => $order->id
                        ],
                        'method'     => 'patch'
                    ],
                    'back_to_basket' => [
                        'name'       => 'retina.dropshipping.customer_sales_channels.basket.show',
                        'parameters' => [
                            'customerSalesChannel' => $order->customerSalesChannel->slug,
                            'order'                => $order->slug
                        ]
                    ]
                ]
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
                                'name'       => 'retina.dropshipping.checkout.show',
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
