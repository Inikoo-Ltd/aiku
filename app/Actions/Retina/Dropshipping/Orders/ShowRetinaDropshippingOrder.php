<?php

/*
 * author Arya Permana - Kirin
 * created on 04-03-2025-13h-50m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\Order\UI\GetOrderAddressManagement;
use App\Actions\Ordering\Order\UI\ShowOrder;
use App\Actions\Ordering\Transaction\UI\IndexNonProductItems;
use App\Actions\Ordering\Transaction\UI\IndexTransactions;
use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Actions\RetinaAction;
use App\Enums\UI\Ordering\RetinaOrderTabsEnum;
use App\Helpers\NaturalLanguage;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Http\Resources\CRM\CustomerClientResource;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Dispatching\RetinaShipmentsResource;
use App\Http\Resources\Dispatching\ShipmentsResource;
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
use App\Models\Dropshipping\CustomerSalesChannel;

class ShowRetinaDropshippingOrder extends RetinaAction
{
    use GetPlatformLogo;

    public function handle(Order $order): Order
    {
        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route('customerSalesChannel');
        if ($customerSalesChannel->customer_id == $this->customer->id) {
            return true;
        }

        return false;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, Order $order, ActionRequest $request): Order
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
            'Dropshipping/RetinaDropshippingOrder',
            [
                'title'       => __('order'),
                'breadcrumbs' => $this->getBreadcrumbs($order),
                'pageHead'    => [
                    'title'   => $order->reference,
                    'model'   => __('Order'),
                    'icon'    => [
                        'icon'  => 'fal fa-shopping-cart',
                        'title' => __('customer client')
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

                'address_management' => GetOrderAddressManagement::run(order: $order, isRetina: true),

                'box_stats' => $this->getOrderBoxStats($order),
                'currency'  => CurrencyResource::make($order->currency)->toArray(request()),
                'data'      => OrderResource::make($order),


                RetinaOrderTabsEnum::TRANSACTIONS->value => $this->tab == RetinaOrderTabsEnum::TRANSACTIONS->value ?
                    fn() => TransactionsResource::collection(IndexTransactions::run(parent: $order, prefix: RetinaOrderTabsEnum::TRANSACTIONS->value))
                    : Inertia::lazy(fn() => TransactionsResource::collection(IndexTransactions::run(parent: $order, prefix: RetinaOrderTabsEnum::TRANSACTIONS->value))),


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
            IndexRetinaDropshippingOrders::make()->getBreadcrumbs($order->customerSalesChannel),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'retina.dropshipping.customer_sales_channels.orders.show',
                            'parameters' => [
                                'customerSalesChannel' => $order->customerSalesChannel->slug,
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

        $customerChannel = null;
        if ($order->customer_sales_channel_id) {
            $customerChannel = [
                'slug'     => $order->customerSalesChannel->slug,
                'status'   => $order->customer_sales_channel_id,
                'platform' => [
                    'name'  => $order->platform?->name,
                    'image' => $this->getPlatformLogo($order->customerSalesChannel->platform->code)
                ]
            ];
        }

        $invoicesData = [];

        foreach ($order->invoices as $invoice) {
            $routeShow = [
                'name'       => 'retina.dropshipping.invoices.show',
                'parameters' => [
                    'invoice' => $invoice->slug,
                ],
            ];


            $routeDownload = null;

            $invoicesData[] = [
                'reference' => $invoice->reference,
                'routes'    => [
                    'show'     => $routeShow,
                    'download' => $routeDownload,
                ],
            ];
        }

        $customerClientData = null;

        if ($order->customerClient) {
            $customerClientData = array_merge(
                CustomerClientResource::make($order->customerClient)->getArray(),
                [
                    'route' => [
                        'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show',
                        'parameters' => [
                            'organisation'         => $order->organisation->slug,
                            'shop'                 => $order->shop->slug,
                            'customer'             => $order->customer->slug,
                            'customerSalesChannel' => $order->customerSalesChannel->slug,
                            'customerClient'       => $order->customerClient->ulid
                        ]
                    ]
                ]
            );
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
            'customer_client'  => $customerClientData,
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
            'customer_channel' => $customerChannel,
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
