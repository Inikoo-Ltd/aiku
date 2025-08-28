<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Ecom\Checkout\UI;

use App\Actions\Accounting\OrderPaymentApiPoint\StoreOrderPaymentApiPoint;
use App\Actions\Accounting\Traits\CalculatesPaymentWithBalance;
use App\Actions\Retina\Ecom\Basket\UI\IsOrder;
use App\Actions\Retina\GetRetinaPaymentMethods;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\Sales\OrderResource;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaEcomCheckout extends RetinaAction
{
    use IsOrder;
    use CalculatesPaymentWithBalance;

    public function handle(Customer $customer): array
    {
        $order = $customer->orderInBasket;

        $orderPaymentApiPoint = StoreOrderPaymentApiPoint::run($order);

        $paymentMethods = [];

        if ($order) {
            $paymentMethods = GetRetinaPaymentMethods::run($order, $orderPaymentApiPoint);
        }


        return [
            'order'          => $order,
            'paymentMethods' => $paymentMethods,
            'balance'        => $customer->balance,
        ];
    }


    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);
        $order = $this->customer->orderInBasket;
        if (!$order) {
            return [
                'order'          => null,
                'paymentMethods' => null,
                'balance'        => null,
            ];
        } else {
            return $this->handle($this->customer);
        }
    }

    public function htmlResponse(array $checkoutData): \Illuminate\Http\Response|Response|\Illuminate\Http\RedirectResponse
    {
        /** @var Order $order */
        $order = Arr::get($checkoutData, 'order');

        if (!$order) {
            return Redirect::route('retina.ecom.basket.show');
        }

        $paymentAmounts = $this->calculatePaymentWithBalance(
            $order->total_amount,
            $this->customer->balance
        );

        $toPay          = $paymentAmounts['total'];
        $toPayByBalance = $paymentAmounts['by_balance'];
        $toPayByOther   = $paymentAmounts['by_other'];

        return Inertia::render(
            'Ecom/RetinaEcomCheckout',
            [
                'breadcrumbs'    => $this->getBreadcrumbs(),
                'title'          => __('Checkout'),
                'pageHead'       => [
                    'icon'  => 'fal fa-shopping-cart',
                    'title' => $order->reference,
                    'model' => __('Checkout'),
                ],
                'order'          => OrderResource::make($order)->resolve(),
                'summary'        => $this->getOrderBoxStats($order),
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
                    'back_to_basket'   => [
                        'name'       => 'retina.ecom.basket.show',
                        'parameters' => []
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.ecom.checkout.show'
                            ],
                            'label' => __('Checkout'),
                        ]
                    ]
                ]
            );
    }
}
