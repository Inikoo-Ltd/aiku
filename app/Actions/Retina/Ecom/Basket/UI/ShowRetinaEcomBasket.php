<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Ecom\Basket\UI;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Fulfilment\RetinaEcomBasketTransactionsResources;
use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\CRM\Customer;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaEcomBasket extends RetinaAction
{
    public function handle(Customer $customer, $prefix = null): Order|null
    {
        if (!$customer->current_order_in_basket_id) {
            return null;
        }
        return Order::find($customer->current_order_in_basket_id);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }

    public function asController(ActionRequest $request): Order|null
    {
        $this->initialisation($request);

        $customer = $request->user()->customer;

        return $this->handle($customer);
    }

    public function htmlResponse(Order|null $order): Response
    {
        return Inertia::render(
            'Ecom/Basket',
            [
                    'breadcrumbs' => $this->getBreadcrumbs(),
                    'title'       => __('Baskets'),
                    'pageHead'    => [
                        'title' => __('Baskets'),
                        'icon'  => 'fal fa-shopping-basket'
                    ],


                    'data'     => $order ? $this->getOrderBoxStats($order) : null,

                    'transactions' => $order ? RetinaEcomBasketTransactionsResources::collection(IndexBasketTransactions::run($order)) : null,
                ]
        );
    }

    public function getOrderBoxStats(Order $order): array
    {

        $payAmount   = $order->total_amount - $order->payment_amount;
        $roundedDiff = round($payAmount, 2);

        $estWeight = ($order->estimated_weight ?? 0) / 1000;

        return [
            'customer' => array_merge(
                CustomerResource::make($order->customer)->getArray(),
                [
                    'addresses' => [
                        'delivery' => AddressResource::make($order->deliveryAddress ?? new Address()),
                        'billing'  => AddressResource::make($order->billingAddress ?? new Address())
                    ],
                ]
            ),
            'products' => [
                'payment'          => [
                    'routes'       => [
                        'fetch_payment_accounts' => [
                            'name'       => 'grp.json.shop.payment-accounts',
                            'parameters' => [
                                'shop' => $order->shop->slug
                            ]
                        ],
                        'submit_payment'         => [
                            'name'       => 'grp.models.order.payment.store',
                            'parameters' => [
                                'order' => $order->id
                            ]
                        ]

                    ],
                    'total_amount' => (float)$order->total_amount,
                    'paid_amount'  => (float)$order->payment_amount,
                    'pay_amount'   => $roundedDiff,
                ],
                'estimated_weight' => $estWeight
            ],

            'order_summary' => [
                [
                    [
                        'label'       => 'Items',
                        'quantity'    => $order->stats->number_item_transactions,
                        'price_base'  => 'Multiple',
                        'price_total' => $order->net_amount
                    ],
                ],
                [
                    [
                        'label'       => 'Charges',
                        'information' => '',
                        'price_total' => $order->charges_amount
                    ],
                    [
                        'label'       => 'Shipping',
                        'information' => '',
                        'price_total' => $order->shipping_amount
                    ]
                ],
                [
                    [
                        'label'       => 'Net',
                        'information' => '',
                        'price_total' => $order->net_amount
                    ],
                    [
                        'label'       => 'Tax 20%',
                        'information' => '',
                        'price_total' => $order->tax_amount
                    ]
                ],
                [
                    [
                        'label'       => 'Total',
                        'price_total' => $order->total_amount
                    ]
                ],
                'currency' => CurrencyResource::make($order->currency),
            ],
        ];
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
                                'name' => 'retina.dropshipping.orders.index'
                            ],
                            'label'  => __('Orders'),
                        ]
                    ]
                ]
            );
    }
}
