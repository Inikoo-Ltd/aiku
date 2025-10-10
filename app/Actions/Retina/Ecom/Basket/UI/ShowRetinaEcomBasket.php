<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Ecom\Basket\UI;

use App\Actions\Ordering\Order\UI\GetOrderDeliveryAddressManagement;
use App\Actions\Retina\Ecom\Orders\IndexRetinaEcomOrders;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Http\Resources\Catalogue\ChargeResource;
use App\Http\Resources\Fulfilment\RetinaEcomBasketTransactionsResources;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Http\Resources\Sales\OrderResource;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaEcomBasket extends RetinaAction
{
    use IsOrder;

    public function handle(Customer $customer): Order|null
    {
        if (!$customer->current_order_in_basket_id) {
            return null;
        }

        return Order::find($customer->current_order_in_basket_id);
    }


    public function asController(ActionRequest $request): Order|null
    {
        $this->initialisation($request);

        return $this->handle($this->customer);
    }

    public function htmlResponse(Order|null $order): Response|RedirectResponse
    {
        $isOrder = $order instanceof Order;


        $premiumDispatch = $order?->shop->charges()->where('type', ChargeTypeEnum::PREMIUM)->where('state', ChargeStateEnum::ACTIVE)->first();
        $extraPacking    = $order?->shop->charges()->where('type', ChargeTypeEnum::PACKING)->where('state', ChargeStateEnum::ACTIVE)->first();
        $insurance       = $order?->shop->charges()->where('type', ChargeTypeEnum::INSURANCE)->where('state', ChargeStateEnum::ACTIVE)->first();

        return Inertia::render(
            'Ecom/RetinaEcomBasket',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Basket'),
                'pageHead'    => [
                    'title'      => __('Basket'),
                    'icon'       => 'fal fa-shopping-cart',
                    'afterTitle' => [
                        'label' => $order ? '#'.$order->slug : ''
                    ]
                ],

                'routes' => [
                    'select_products'  => [
                        'name'       => $isOrder ? 'retina.dropshipping.select_products_for_basket' : 'retina.dropshipping.select_products_for_empty_basket',
                        'parameters' => $isOrder ? [
                            'order' => $order->id
                        ] : [],
                    ],
                    'update_route'     => [
                        'name'       => 'retina.models.order.update',
                        'parameters' => [
                            'order' => $order?->id
                        ],
                        'method'     => 'patch'
                    ],
                    'submit_route'     => [
                        'name'       => 'retina.models.order.submit',
                        'parameters' => [
                            'order' => $order?->id
                        ],
                        'method'     => 'patch'
                    ],
                    'pay_with_balance' => [
                        'name'       => 'retina.models.order.pay_with_balance',
                        'parameters' => [
                            'order' => $order?->id
                        ],
                        'method'     => 'patch'
                    ],
                ],

                'voucher' => [],

                'order'   => $order ? OrderResource::make($order)->resolve() : null,
                'summary' => $order
                    ? $this->getOrderBoxStats($order)
                    : [
                        'order_summary' => [
                            [
                                [
                                    'label'       => __('Items'),
                                    'quantity'    => 0,
                                    'price_base'  => 'Multiple',
                                    'price_total' => 0
                                ],
                            ],
                            [
                                [
                                    'label'       => __('Charges'),
                                    'information' => '',
                                    'price_total' => 0
                                ],
                                [
                                    'label'       => __('Shipping'),
                                    'information' => '',
                                    'price_total' => 0
                                ]
                            ],
                            [
                                [
                                    'label'       => __('Net'),
                                    'information' => '',
                                    'price_total' => 0
                                ],
                            ],
                            [
                                [
                                    'label'       => __('Total'),
                                    'price_total' => 0
                                ],
                            ],
                        ]
                    ],

                'is_unable_dispatch' => $order && in_array($order->deliveryAddress->country_id, array_merge($order->organisation->forbidden_dispatch_countries ?? [], $order->shop->forbidden_dispatch_countries ?? [])),

                'charges' => [
                    'premium_dispatch' => $premiumDispatch ? ChargeResource::make($premiumDispatch)->toArray(request()) : null,
                    'extra_packing'   => $extraPacking ? ChargeResource::make($extraPacking)->toArray(request()) : null,
                    'insurance'       => $insurance ? ChargeResource::make($insurance)->toArray(request()) : null,
                ],

                'contact_address'    => $order ? AddressResource::make($order->customer->address)->getArray() : null,
                'address_management' => $order ? GetOrderDeliveryAddressManagement::run(order: $order, isRetina: true) : [],
                'balance'            => $this->customer->balance,
                'is_in_basket'       => true,
                'total_to_pay'       => $order ? max(0, $order->total_amount - $order->customer->balance) : 0,
                'total_products'     => $order ? $order->transactions->whereIn('model_type', ['Product', 'Service'])->count() : 0,
                'transactions'       => $order ? RetinaEcomBasketTransactionsResources::collection(IndexBasketTransactions::run($order)) : null,
            ]
        )->table(
            IndexBasketTransactions::make()->tableStructure()
        );
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                IndexRetinaEcomOrders::make()->getBreadcrumbs(),
                [
                    // [
                    //     'type'   => 'simple',
                    //     'simple' => [
                    //         'route' => [
                    //             'name' => 'retina.dropshipping.orders.index'
                    //         ],
                    //         'label'  => __('Orders'),
                    //     ]
                    // ]
                ]
            );
    }
}
