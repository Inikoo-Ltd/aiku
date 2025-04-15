<?php

/*
 * author Arya Permana - Kirin
 * created on 04-03-2025-13h-50m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\Invoice\UI\IndexInvoices;
use App\Actions\Dispatching\DeliveryNote\UI\IndexDeliveryNotes;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\Ordering\Order\UI\ShowOrder;
use App\Actions\Ordering\Transaction\UI\IndexNonProductItems;
use App\Actions\Ordering\Transaction\UI\IndexTransactions;
use App\Actions\RetinaAction;
use App\Enums\UI\Ordering\OrderTabsEnum;
use App\Http\Resources\Accounting\InvoicesResource;
use App\Http\Resources\Ordering\TransactionsResource;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Dispatching\DeliveryNotesResource;
use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Http\Resources\Ordering\NonProductItemsResource;
use App\Models\Dropshipping\Platform;
use App\Models\Helpers\Address;
use Illuminate\Support\Facades\DB;

class ShowRetinaDropshippingOrder extends RetinaAction
{
    public function handle(Order $order): Order
    {
        return $order;
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    public function inPlatform(Platform $platform, Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromPlatform($platform, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }



    public function htmlResponse(Order $order, ActionRequest $request): Response
    {


        $finalTimeline = ShowOrder::make()->getOrderTimeline($order);

        $addresses = $order->customer->addresses;

        $processedAddresses = $addresses->map(function ($address) {
            if (!DB::table('model_has_addresses')->where('address_id', $address->id)->where('model_type', '=', 'Customer')->exists()) {
                return $address->setAttribute('can_delete', false)
                    ->setAttribute('can_edit', true);
            }


            return $address->setAttribute('can_delete', true)
                ->setAttribute('can_edit', true);
        });

        $customerAddressId         = $order->customer->address->id;
        $customerDeliveryAddressId = $order->customer->deliveryAddress->id;
        $orderDeliveryAddressIds   = Order::where('customer_id', $order->customer_id)
            ->pluck('delivery_address_id')
            ->unique()
            ->toArray();

        $forbiddenAddressIds = array_merge(
            $orderDeliveryAddressIds,
            [$customerAddressId, $customerDeliveryAddressId]
        );

        $processedAddresses->each(function ($address) use ($forbiddenAddressIds) {
            if (in_array($address->id, $forbiddenAddressIds, true)) {
                $address->setAttribute('can_delete', false)
                    ->setAttribute('can_edit', true);
            }
        });

        $addressCollection = AddressResource::collection($processedAddresses);

        $payAmount   = $order->total_amount - $order->payment_amount;
        $roundedDiff = round($payAmount, 2);

        $estWeight = ($order->estimated_weight ?? 0) / 1000;

        $nonProductItems = NonProductItemsResource::collection(IndexNonProductItems::run($order));

        $actions = [];


        if ($this->canEdit) {
            $actions = match ($order->state) {
                OrderStateEnum::CREATING => [
                    [
                        'type'    => 'button',
                        'style'   => 'secondary',
                        'icon'    => 'fal fa-plus',
                        'key'     => 'add-products',
                        'label'   => __('add products'),
                        'tooltip' => __('Add products'),
                        'route'   => [
                            // 'name'       => 'grp.models.order.transaction.store',
                            // 'parameters' => [
                            //     'order' => $order->id,
                            // ]
                        ]
                    ],
                    ($order->transactions()->count() > 0) ?
                        [
                            'type'    => 'button',
                            'style'   => 'save',
                            'tooltip' => __('submit'),
                            'label'   => __('submit'),
                            'key'     => 'action',
                            'route'   => [
                                // 'method'     => 'patch',
                                // 'name'       => 'grp.models.order.state.submitted',
                                // 'parameters' => [
                                //     'order' => $order->id
                                // ]
                            ]
                        ] : [],
                ],
                OrderStateEnum::SUBMITTED => [
                    [
                        'type'    => 'button',
                        'style'   => 'save',
                        'tooltip' => __('Send to Warehouse'),
                        'label'   => __('send to warehouse'),
                        'key'     => 'action',
                        'route'   => [
                            // 'method'     => 'patch',
                            // 'name'       => 'grp.models.order.state.in-warehouse',
                            // 'parameters' => [
                            //     'order' => $order->id
                            // ]
                        ]
                    ]
                ],
                OrderStateEnum::IN_WAREHOUSE => [
                    [
                        'type'    => 'button',
                        'style'   => 'save',
                        'tooltip' => __('Handle'),
                        'label'   => __('Handle'),
                        'key'     => 'action',
                        'route'   => [
                            // 'method'     => 'patch',
                            // 'name'       => 'grp.models.order.state.handling',
                            // 'parameters' => [
                            //     'order' => $order->id
                            // ]
                        ]
                    ]
                ],
                OrderStateEnum::HANDLING => [
                    [
                        'type'    => 'button',
                        'style'   => 'save',
                        'tooltip' => __('Pack'),
                        'label'   => __('Pack'),
                        'key'     => 'action',
                        'route'   => [
                            // 'method'     => 'patch',
                            // 'name'       => 'grp.models.order.state.packed',
                            // 'parameters' => [
                            //     'order' => $order->id
                            // ]
                        ]
                    ]
                ],
                OrderStateEnum::PACKED => [
                    [
                        'type'    => 'button',
                        'style'   => 'save',
                        'tooltip' => __('Finalize'),
                        'label'   => __('Finalize'),
                        'key'     => 'action',
                        'route'   => [
                            // 'method'     => 'patch',
                            // 'name'       => 'grp.models.order.state.finalized',
                            // 'parameters' => [
                            //     'order' => $order->id
                            // ]
                        ]
                    ]
                ],
                OrderStateEnum::FINALISED => [
                    [
                        'type'    => 'button',
                        'style'   => 'save',
                        'tooltip' => __('Dispatch'),
                        'label'   => __('Dispatch'),
                        'key'     => 'action',
                        'route'   => [
                            // 'method'     => 'patch',
                            // 'name'       => 'grp.models.order.state.dispatched',
                            // 'parameters' => [
                            //     'order' => $order->id
                            // ]
                        ]
                    ]
                ],
                default => []
            };
        }

        $deliveryNoteRoute    = null;
        $deliveryNoteResource = null;
        if ($order->deliveryNotes()->first()) {
            $deliveryNoteRoute = [
                    'deliveryNoteRoute' => [
                        // 'name'        => 'grp.org.shops.show.ordering.orders.show.delivery-note',
                        // 'parameters'  => array_merge($request->route()->originalParameters(), [
                        //     'deliveryNote' => $order->deliveryNotes()->first()->slug
                        // ])
                        ],
                    'deliveryNotePdfRoute' => [
                        // 'name' => 'grp.org.warehouses.show.dispatching.delivery-notes.pdf',
                        // 'parameters' => [
                        //     'organisation' =>  $order->organisation->slug,
                        //     'warehouse' => $order->deliveryNotes->first()->warehouse->slug,
                        //     'deliveryNote' => $order->deliveryNotes()->first()->slug,
                        // ],
                    ]
            ];

            $deliveryNoteResource = DeliveryNotesResource::make($order->deliveryNotes()->first());

        }

        $customerAddressId              = $order->customer->address->id;
        $customerDeliveryAddressId      = $order->customer->deliveryAddress->id;
        $orderDeliveryAddressIds = Order::where('customer_id', $order->customer_id)
                                            ->pluck('delivery_address_id')
                                            ->unique()
                                            ->toArray();

        $forbiddenAddressIds = array_merge(
            $orderDeliveryAddressIds,
            [$customerAddressId, $customerDeliveryAddressId]
        );

        $processedAddresses->each(function ($address) use ($forbiddenAddressIds) {
            if (in_array($address->id, $forbiddenAddressIds, true)) {
                $address->setAttribute('can_delete', false)
                        ->setAttribute('can_edit', true);
            }
        });

        $addressCollection = AddressResource::collection($processedAddresses);

        return Inertia::render(
            'Dropshipping/Order',
            [
                'title'       => __('order'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'pageHead'    => [
                    'title'   => $order->reference,
                    'model'   => __('Order'),
                    'icon'    => [
                        'icon'  => 'fal fa-shopping-cart',
                        'title' => __('customer client')
                    ],
                    'actions' => $actions
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => OrderTabsEnum::navigation()
                ],
                'routes'      => [
                    'updateOrderRoute' => [
                        // 'method'     => 'patch',
                        // 'name'       => 'grp.models.order.update',
                        // 'parameters' => [
                        //     'order' => $order->id,
                        // ]
                    ],
                    'products_list'    => [
                        // 'name'       => 'grp.json.order.products',
                        // 'parameters' => [
                        //     'order' => $order->id
                        // ]
                    ],
                    'delivery_note' => $deliveryNoteRoute
                ],
                // 'alert'   => [  // TODO
                //     'status'        => 'danger',
                //     'title'         => 'Dummy Alert from BE',
                //     'description'   => 'Dummy description'
                // ],
                'notes'       => [
                    "note_list" => [
                        [
                            "label"    => __("Customer"),
                            "note"     => $order->customer_notes ?? '',
                            "editable" => false,
                            "bgColor"  => "#FF7DBD",
                            "field"    => "customer_notes"
                        ],
                        [
                            "label"    => __("Public"),
                            "note"     => $order->public_notes ?? '',
                            "editable" => true,
                            "bgColor"  => "#94DB84",
                            "field"    => "public_notes"
                        ],
                        [
                            "label"    => __("Private"),
                            "note"     => $order->internal_notes ?? '',
                            "editable" => true,
                            "bgColor"  => "#FCF4A3",
                            "field"    => "internal_notes"
                        ]
                    ]
                ],
                'timelines'   => $finalTimeline,
                // 'address_update_route'  => [
                //     'method'     => 'patch',
                //     'name'       => 'grp.models.customer.address.update',
                //     'parameters' => [
                //         'customer' => $order->customer_id
                //     ]
                // ],
                'addresses'   => [
                    'isCannotSelect'                => true,
                    'address_list'                  => $addressCollection,
                    'options'                       => [
                        'countriesAddressData' => GetAddressData::run()
                    ],
                    'pinned_address_id'              => $order->customer->delivery_address_id,
                    'home_address_id'                => $order->customer->address_id,
                    'current_selected_address_id'    => $order->customer->delivery_address_id,
                    'selected_delivery_addresses_id' => $orderDeliveryAddressIds,
                    'routes_list'                    => [
                        'pinned_route'                   => [
                            // 'method'     => 'patch',
                            // 'name'       => 'grp.models.customer.delivery-address.update',
                            // 'parameters' => [
                            //     'customer' => $order->customer_id
                            // ]
                        ],
                        'delete_route'  => [
                            // 'method'     => 'delete',
                            // 'name'       => 'grp.models.customer.delivery-address.delete',
                            // 'parameters' => [
                            //     'customer' => $order->customer_id
                            // ]
                        ],
                        'store_route' => [
                            // 'method'      => 'post',
                            // 'name'        => 'grp.models.customer.address.store',
                            // 'parameters'  => [
                            //     'customer' => $order->customer_id
                            // ]
                        ]
                    ]
                ],

                'box_stats'      => [
                    'customer'      => array_merge(
                        CustomerResource::make($order->customer)->getArray(),
                        [
                            'addresses' => [
                                'delivery' => AddressResource::make($order->deliveryAddress ?? new Address()),
                                'billing'  => AddressResource::make($order->billingAddress ?? new Address())
                            ],
                        ]
                    ),
                    'products'      => [
                        'payment'          => [
                            'routes'       => [
                                'fetch_payment_accounts' => [
                                    // 'name'       => 'grp.json.shop.payment-accounts',
                                    // 'parameters' => [
                                    //     'shop' => $order->shop->slug
                                    // ]
                                ],
                                'submit_payment'         => [
                                    // 'name'       => 'grp.models.order.payment.store',
                                    // 'parameters' => [
                                    //     'order'    => $order->id,
                                    // ]
                                ]

                            ],
                            'total_amount' => (float) $order->total_amount,
                            'paid_amount'  => (float) $order->payment_amount,
                            'pay_amount'   => $roundedDiff,
                        ],
                        'estimated_weight' => $estWeight
                    ],

                    'order_summary' => [
                        [
                            [
                                'label'       => 'Items',
                                'quantity'    => $order->stats->number_transactions,
                                'price_base'  => 'Multiple',
                                'price_total' => $order->net_amount
                            ],
                        ],
                        [
                            [
                                'label'       => 'Charges',
                                'information' => '',
                                'price_total' => '0'
                            ],
                            [
                                'label'       => 'Shipping',
                                'information' => '',
                                'price_total' => '0'
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
                ],
                'currency'       => CurrencyResource::make($order->currency)->toArray(request()),
                'data'           => OrderResource::make($order),
                'delivery_note'  => $deliveryNoteResource,

                'attachmentRoutes' => [
                    'attachRoute' => [
                        // 'name' => 'grp.models.order.attachment.attach',
                        // 'parameters' => [
                        //     'order' => $order->id,
                        // ]
                    ],
                    'detachRoute' => [
                        // 'name' => 'grp.models.order.attachment.detach',
                        // 'parameters' => [
                        //     'order' => $order->id,
                        // ],
                        // 'method' => 'delete'
                    ]
                ],
                // 'nonProductItems' => $nonProductItems,
                // 'showcase'=> GetOrderShowcase::run($order),


                OrderTabsEnum::TRANSACTIONS->value => $this->tab == OrderTabsEnum::TRANSACTIONS->value ?
                    fn () => TransactionsResource::collection(IndexTransactions::run(parent: $order, prefix: OrderTabsEnum::TRANSACTIONS->value))
                    : Inertia::lazy(fn () => TransactionsResource::collection(IndexTransactions::run(parent: $order, prefix: OrderTabsEnum::TRANSACTIONS->value))),

                 OrderTabsEnum::INVOICES->value => $this->tab == OrderTabsEnum::INVOICES->value ?
                     fn () => InvoicesResource::collection(IndexInvoices::run(parent: $order, prefix: OrderTabsEnum::TRANSACTIONS->value))
                     : Inertia::lazy(fn () => InvoicesResource::collection(IndexInvoices::run(parent: $order, prefix: OrderTabsEnum::TRANSACTIONS->value))),

                 OrderTabsEnum::DELIVERY_NOTES->value => $this->tab == OrderTabsEnum::DELIVERY_NOTES->value ?
                     fn () => DeliveryNotesResource::collection(IndexDeliveryNotes::run(parent: $order, prefix: OrderTabsEnum::DELIVERY_NOTES->value))
                     : Inertia::lazy(fn () => DeliveryNotesResource::collection(IndexDeliveryNotes::run(parent: $order, prefix: OrderTabsEnum::DELIVERY_NOTES->value))),

                 OrderTabsEnum::ATTACHMENTS->value => $this->tab == OrderTabsEnum::ATTACHMENTS->value ?
                     fn () => AttachmentsResource::collection(IndexAttachments::run(parent: $order, prefix: OrderTabsEnum::DELIVERY_NOTES->value))
                     : Inertia::lazy(fn () => AttachmentsResource::collection(IndexAttachments::run(parent: $order, prefix: OrderTabsEnum::DELIVERY_NOTES->value))),

            ]
        )
            ->table(
                IndexTransactions::make()->tableStructure(
                    parent: $order,
                    tableRows: $nonProductItems,
                    prefix: OrderTabsEnum::TRANSACTIONS->value
                )
            )
            ->table(IndexInvoices::make()->tableStructure(
                parent: $order,
                prefix: OrderTabsEnum::INVOICES->value
            ))
            ->table(IndexAttachments::make()->tableStructure(
                prefix: OrderTabsEnum::ATTACHMENTS->value
            ))
            ->table(IndexDeliveryNotes::make()->tableStructure(
                parent: $order,
                prefix: OrderTabsEnum::DELIVERY_NOTES->value
            ));
    }

    public function jsonResponse(Order $order): OrderResource
    {
        return new OrderResource($order);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = ''): array
    {
        $headCrumb = function (Order $order, array $routeParameters, string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __($order->slug),
                    ],
                ],
            ];
        };

        $order = Order::where('slug', $routeParameters['order'])->first();

        return array_merge(
            IndexRetinaDropshippingOrders::make()->getBreadcrumbs(),
            $headCrumb(
                $order,
                [
                    'index' => [
                        'name' => 'retina.dropshipping.orders.index',
                        'parameters' => []
                    ],
                    'model' => [
                        'name' => 'retina.dropshipping.orders.show',
                        'parameters' => [$order->slug]
                    ]
                ],
                $suffix
            ),
        );
    }
}
