<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Aug 2025 14:56:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ecom\Orders;

use App\Actions\Ordering\Order\UI\ShowOrder;
use App\Actions\Ordering\Transaction\UI\IndexNonProductItems;
use App\Actions\Ordering\Transaction\UI\IndexTransactions;
use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Actions\RetinaAction;
use App\Enums\UI\Ordering\RetinaOrderTabsEnum;
use App\Helpers\NaturalLanguage;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Dispatching\RetinaShipmentsResource;
use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Http\Resources\Ordering\NonProductItemsResource;
use App\Http\Resources\Ordering\TransactionsResource;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaEcomOrder extends RetinaAction
{
    use GetPlatformLogo;

    public function handle(Order $order): Order
    {
        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');
        if ($order->customer_id == $this->customer->id) {
            return true;
        }

        return false;
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request)->withTab(RetinaOrderTabsEnum::values());

        return $this->handle($order);
    }


    public function htmlResponse(Order $order, ActionRequest $request): Response
    {
        $finalTimeline = ShowOrder::make()->getOrderTimeline($order);


        $nonProductItems = NonProductItemsResource::collection(IndexNonProductItems::run($order));

        $action = [];

        $this->tab = $this->tab ?: RetinaOrderTabsEnum::TRANSACTIONS->value;

        return Inertia::render(
            'Ecom/RetinaEcomOrder',
            [
                'title'       => __('order'),
                'breadcrumbs' => $this->getBreadcrumbs($order),
                'pageHead'    => [
                    'title'   => $order->reference,
                    'model'   => __('Order'),
                    'icon'    => [
                        'icon'  => 'fal fa-shopping-basket',
                        'title' => __('Order')
                    ],
                    'actions' => $action,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => RetinaOrderTabsEnum::navigation()
                ],

                'routes' => [
                    'update_route'        => [
                        'name'       => 'retina.models.order.update',
                        'parameters' => [
                            'order' => $order->id
                        ],
                        'method'     => 'patch'
                    ],
                    'submit_route'        => [
                        'name'       => 'retina.models.order.submit',
                        'parameters' => [
                            'order' => $order->id
                        ],
                        'method'     => 'patch'
                    ],
                    'route_to_pay_unpaid' => [
                        'name'       => 'retina.json.get_checkout_com_token_to_pay_order',
                        'parameters' => [
                            'order' => $order->id,
                        ],
                    ],


                ],

                'timelines' => $finalTimeline,
                'box_stats' => $this->getOrderBoxStats($order),
                'currency'  => CurrencyResource::make($order->currency)->toArray(request()),
                'data'      => OrderResource::make($order),
                'is_notes_editable' => false,  // TODO: make it dynamic, only disable on 'after' state

                RetinaOrderTabsEnum::TRANSACTIONS->value => $this->tab == RetinaOrderTabsEnum::TRANSACTIONS->value ?
                    fn () => TransactionsResource::collection(IndexTransactions::run(parent: $order, prefix: RetinaOrderTabsEnum::TRANSACTIONS->value))
                    : Inertia::lazy(fn () => TransactionsResource::collection(IndexTransactions::run(parent: $order, prefix: RetinaOrderTabsEnum::TRANSACTIONS->value))),


            ]
        )
            ->table(
                IndexTransactions::make()->tableStructure(
                    parent: $order,
                    tableRows: $nonProductItems,
                    prefix: RetinaOrderTabsEnum::TRANSACTIONS->value
                )
            );
    }

    public function jsonResponse(Order $order): OrderResource
    {
        return new OrderResource($order);
    }

    public function getBreadcrumbs(Order $order): array
    {
        return array_merge(
            IndexRetinaEcomOrders::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'retina.ecom.orders.show',
                            'parameters' => [
                                'order'                => $order->slug
                            ]
                        ],
                        'label' => $order->reference,
                    ]
                ]
            ]
        );
    }

    public function getOrderBoxStats(Order $order): array
    {
        $payAmount   = $order->total_amount - $order->payment_amount;
        $roundedDiff = round($payAmount, 2);

        $estWeight = ($order->estimated_weight ?? 0) / 1000;



        $invoicesData = [];

        foreach ($order->invoices as $invoice) {
            $routeShow = [
                'name'       => 'retina.ecom.invoices.show',
                'parameters' => [
                    'invoice' => $invoice->slug,
                ],
            ];

            $routeDownload = [
                'name'       => 'retina.ecom.invoices.pdf',
                'parameters' => [
                    'invoice' => $invoice->slug,
                ],
            ];

            $invoicesData[] = [
                'reference' => $invoice->reference,
                'routes'    => [
                    'show'     => $routeShow,
                    'download' => $routeDownload,
                ],
            ];
        }



        $deliveryNotes     = $order->deliveryNotes;
        $deliveryNotesData = [];

        if ($deliveryNotes) {
            foreach ($deliveryNotes as $deliveryNote) {
                $deliveryNotesData[] = [
                    'id'        => $deliveryNote->id,
                    'reference' => $deliveryNote->reference,
                    'state'     => $deliveryNote->state->stateIcon()[$deliveryNote->state->value],
                    'shipments' => $deliveryNote?->shipments ? RetinaShipmentsResource::collection($deliveryNote->shipments()->with('shipper')->get())->resolve() : null
                ];
            }
        }


        return [
            'customer'         => array_merge(
                CustomerResource::make($order->customer)->getArray(),
                [
                    'addresses' => [
                        'delivery' => AddressResource::make($order->deliveryAddress ?? new Address()),
                        'billing'  => AddressResource::make($order->billingAddress ?? new Address())
                    ],
                    'route'     => [
                        'name'       => 'grp.org.shops.show.crm.customers.show',
                        'parameters' => [
                            'organisation' => $order->organisation->slug,
                            'shop'         => $order->shop->slug,
                            'customer'     => $order->customer->slug,
                        ]
                    ]
                ]
            ),
            'invoices'         => $invoicesData,
            'order_properties' => [
                'weight'    => NaturalLanguage::make()->weight($order->estimated_weight),
            ],
            'delivery_notes'   => $deliveryNotesData,
            'products'         => [
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
                    'pay_status'   => $order->pay_status,
                ],
                'estimated_weight' => $estWeight,
            ],

            'payments' => PaymentsResource::collection($order->payments)->toArray(request()),

            'order_summary' => [
                [
                    [
                        'label'       => 'Items',
                        'quantity'    => $order->stats->number_item_transactions,
                        'price_base'  => 'Multiple',
                        'price_total' => $order->goods_amount
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
                    ],
                ],

                'currency' => CurrencyResource::make($order->currency),
            ],
        ];
    }

}
