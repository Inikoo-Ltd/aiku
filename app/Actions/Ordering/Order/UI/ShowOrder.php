<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\Accounting\Invoice\UI\IndexInvoicesInOrder;
use App\Actions\Accounting\Payment\UI\IndexPayments;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\CRM\Customer\UI\ShowCustomerClient;
use App\Actions\Dispatching\DeliveryNote\UI\IndexDeliveryNotes;
use App\Actions\Dropshipping\CustomerSalesChannel\UI\ShowCustomerSalesChannel;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\Ordering\Purge\UI\ShowPurge;
use App\Actions\Ordering\Transaction\UI\IndexNonProductItems;
use App\Actions\Ordering\Transaction\UI\IndexTransactions;
use App\Actions\OrgAction;
use App\Actions\Retina\Ecom\Basket\UI\IsOrder;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\UI\Ordering\OrderTabsEnum;
use App\Http\Resources\Accounting\InvoicesResource;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Http\Resources\Dispatching\DeliveryNotesResource;
use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Http\Resources\Ordering\NonProductItemsResource;
use App\Http\Resources\Ordering\TransactionsResource;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Ordering\Order;
use App\Models\Ordering\Purge;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrder extends OrgAction
{
    use IsOrder;
    use WithOrderingEditAuthorisation;

    private Shop|Customer|CustomerClient|Purge|CustomerSalesChannel $parent;
    private CustomerSalesChannel $customerSalesChannel;

    public function handle(Order $order): Order
    {
        return $order;
    }

    public function inOrganisation(Organisation $organisation, Order $order, ActionRequest $request): Order
    {
        $this->initialisation($organisation, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    public function asController(Organisation $organisation, Shop $shop, Order $order, ActionRequest $request): Order
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inCustomerInShop(Organisation $organisation, Shop $shop, Customer $customer, Order $order, ActionRequest $request): Order
    {
        $this->parent = $customer;
        $this->initialisationFromShop($shop, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inPlatformInCustomer(Organisation $organisation, Shop $shop, Customer $customer, CustomerSalesChannel $customerSalesChannel, Order $order, ActionRequest $request): Order
    {
        $this->parent = $customerSalesChannel;
        $this->initialisationFromShop($shop, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inCustomerClient(Organisation $organisation, Shop $shop, Customer $customer, CustomerSalesChannel $customerSalesChannel, CustomerClient $customerClient, Order $order, ActionRequest $request): Order
    {
        $this->parent               = $customerClient;
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisationFromShop($shop, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomerClient(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, CustomerClient $customerClient, CustomerSalesChannel $customerSalesChannel, Order $order, ActionRequest $request): Order
    {
        $this->parent               = $customerClient;
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inPurge(Organisation $organisation, Shop $shop, Purge $purge, Order $order, ActionRequest $request): Order
    {
        $this->parent = $purge;
        $this->initialisationFromShop($shop, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }


    public function getOrderTimeline(Order $order): array
    {
        $timeline = [];
        foreach (OrderStateEnum::cases() as $state) {
            if ($state === OrderStateEnum::CREATING) {
                $timestamp = $order->created_at;
            } else {
                $timestamp = $order->{$state->snake().'_at'} ? $order->{$state->snake().'_at'} : null;
            }

            // If all possible values are null, set the timestamp to null explicitly
            $timestamp = $timestamp ?: null;

            $timeline[$state->value] = [
                'label'     => $state->labels()[$state->value],
                'tooltip'   => $state->labels()[$state->value],
                'key'       => $state->value,
                /* 'icon'    => $palletDelivery->state->stateIcon()[$state->value]['icon'], */
                'timestamp' => $timestamp
            ];
        }

        return Arr::except(
            $timeline,
            [
                $order->state->value == OrderStateEnum::CANCELLED->value
                    ? OrderStateEnum::DISPATCHED->value
                    : OrderStateEnum::CANCELLED->value
            ]
        );
    }

    public function getOrderNotes(Order $order): array
    {
        return [
            "note_list" => [
                [
                    "label"       => __("Delivery Instructions"),
                    "note"        => $order->shipping_notes ?? '',
                    "information" => __("This note is from the customer. Will be printed in the shipping label."),
                    "editable"    => true,
                    "bgColor"     => "#38bdf8",
                    "field"       => "shipping_notes"
                ],
                [
                    "label"       => __("Customer"),
                    "note"        => $order->customer_notes ?? '',
                    "information" => __("This note is from customer in the platform. Not editable."),
                    "editable"    => false,
                    "bgColor"     => "#FF7DBD",
                    "field"       => "customer_notes"
                ],
                [
                    "label"       => __("Public"),
                    "note"        => $order->public_notes ?? '',
                    "information" => __("This note will be visible to public, both staff and the customer can see."),
                    "editable"    => true,
                    "bgColor"     => "#94DB84",
                    "field"       => "public_notes"
                ],
                [
                    "label"       => __("Private"),
                    "note"        => $order->internal_notes ?? '',
                    "information" => __("This note is only visible to staff members. You can communicate each other about the order."),
                    "editable"    => true,
                    "bgColor"     => "#FCF4A3",
                    "field"       => "internal_notes"
                ]
            ]
        ];
    }


    public function htmlResponse(Order $order, ActionRequest $request): Response
    {
        $finalTimeline = $this->getOrderTimeline($order);

        $nonProductItems = NonProductItemsResource::collection(IndexNonProductItems::run($order));

        $actions = $order->shop->type == ShopTypeEnum::DROPSHIPPING
            ?
            GetDropshippingOrderActions::run($order, $this->canEdit)
            :
            GetEcomOrderActions::run($order, $this->canEdit);

        $deliveryNoteRoute    = null;
        $deliveryNoteResource = null;

        /** @var DeliveryNote $firstDeliveryNote */
        $firstDeliveryNote = $order->deliveryNotes()->first();

        if ($firstDeliveryNote) {
            $deliveryNoteRoute = [
                'deliveryNoteRoute'    => [
                    'name'       => 'grp.org.shops.show.ordering.orders.show.delivery-note',
                    'parameters' => array_merge($request->route()->originalParameters(), [
                        'deliveryNote' => $firstDeliveryNote->slug
                    ])
                ],
                'deliveryNotePdfRoute' => [
                    'name'       => 'grp.pdfs.delivery-notes',
                    'parameters' => [
                        'deliveryNote' => $firstDeliveryNote->slug,
                    ],
                ]
            ];

            $deliveryNoteResource = DeliveryNotesResource::make($firstDeliveryNote);
        }

        $platform = $order->platform;
        if (!$platform) {
            $platform = Platform::where('type', PlatformTypeEnum::MANUAL)->first();
        }

        $readonly = false;


        return Inertia::render(
            'Org/Ordering/Order',
            [
                'title'       => __('order'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $order,
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($order, $request),
                    'next'     => $this->getNext($order, $request),
                ],
                'pageHead'    => [
                    'title'      => $order->reference,
                    'model'      => __('Order'),
                    'icon'       => [
                        'icon'  => 'fal fa-shopping-cart',
                        'title' => __('Customer client')
                    ],
                    'afterTitle' => [
                        'label' => $order->state->labels()[$order->state->value],
                    ],
                    'actions'    => $actions,
                    'platform'   => $platform ? [
                        'icon'  => $platform->imageSources(24, 24),
                        'type'  => $platform->type,
                        'title' => __('Platform :platform', ['platform' => $platform->name]),
                    ] : null,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => OrderTabsEnum::navigation()
                ],
                'shop_type'   => $order->shop->type,
                'routes'      => [
                    'modify'                     => [
                        'name'       => 'grp.models.order.modification.save',
                        'parameters' => [
                            'order' => $order->id
                        ]
                    ],
                    'updateOrderRoute'           => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.order.update',
                        'parameters' => [
                            'order' => $order->id,
                        ]
                    ],
                    'rollback_dispatch'          => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.order.rollback_dispatch',
                        'parameters' => [
                            'order' => $order->id
                        ]
                    ],
                    'products_list'              => [
                        'name'       => 'grp.json.order.products',
                        'parameters' => [
                            'order' => $order->id
                        ]
                    ],
                    'products_list_modification' => [
                        'name'       => 'grp.json.order.products_for_modify',
                        'parameters' => [
                            'order' => $order->id
                        ]
                    ],
                    'delivery_note'              => $deliveryNoteRoute
                ],

                'notes'                       => $this->getOrderNotes($order),
                'timelines'                   => $finalTimeline,
                'readonly'                    => $readonly,
                'delivery_address_management' => GetOrderDeliveryAddressManagement::run(order: $order),
                'contact_address'             => AddressResource::make($order->customer->address)->getArray(),
                'box_stats'                   => $this->getOrderBoxStats($order),
                'currency'                    => CurrencyResource::make($order->currency)->toArray(request()),
                'data'                        => OrderResource::make($order),
                'delivery_note'               => $deliveryNoteResource,

                'proforma_invoice' => [
                    'check_list'         => [
                        [
                            'label' => __('Pro mode'),
                            'value' => 'pro_mode',
                        ],
                        [
                            'label' => __('Recommended retail prices'),
                            'value' => 'rrp',
                        ],
                        [
                            'label' => __('Parts'),
                            'value' => 'parts',
                        ],
                        [
                            'label' => __('Commodity Codes'),
                            'value' => 'commodity_codes',
                        ],
                        [
                            'label' => __('Barcode'),
                            'value' => 'barcode',
                        ],
                        [
                            'label' => __('Weight'),
                            'value' => 'weight',
                        ],
                        [
                            'label' => __('Country of Origin'),
                            'value' => 'country_of_origin',
                        ],
                        [
                            'label' => __('Hide Payment Status'),
                            'value' => 'hide_payment_status',
                        ],
                        [
                            'label' => __('CPNP'),
                            'value' => 'cpnp',
                        ],
                        [
                            'label' => __('Group by Tariff Code'),
                            'value' => 'group_by_tariff_code',
                        ],
                    ],
                    'route_download_pdf' => [
                        'name'       => 'grp.org.shops.show.ordering.proforma_invoice.download',
                        'parameters' => [
                            'organisation' => $order->organisation->slug,
                            'shop'         => $order->shop->slug,
                            'order'        => $order->slug
                        ]
                    ]
                ],
                'attachmentRoutes' => [
                    'attachRoute' => [
                        'name'       => 'grp.models.order.attachment.attach',
                        'parameters' => [
                            'order' => $order->id,
                        ]
                    ],
                    'detachRoute' => [
                        'name'       => 'grp.models.order.attachment.detach',
                        'parameters' => [
                            'order' => $order->id,
                        ],
                        'method'     => 'delete'
                    ]
                ],

                'upload_excel' => [
                    'title'               => [
                        'label'       => __('Upload product'),
                        'information' => __('The list of column file: code, quantity')
                    ],
                    'progressDescription' => __('Adding Products'),
                    'preview_template'    => [
                        'header' => ['code', 'quantity'],
                        'rows'   => [
                            [
                                'code'     => 'product-001',
                                'quantity' => '1'
                            ]
                        ]
                    ],
                    'upload_spreadsheet'  => [
                        'event'           => 'action-progress',
                        'channel'         => 'grp.personal.'.$this->organisation->id,
                        'required_fields' => ['code', 'quantity'],
                        'template'        => [
                            'label' => 'Download template (.xlsx)'
                        ],
                        'route'           => [
                            'upload'   => [
                                'name'       => 'grp.models.order.transaction.upload',
                                'parameters' => [
                                    'order' => $order->id
                                ]
                            ],
                            'history'  => [
                                'name'       => 'grp.json.order.transaction.recent_uploads',
                                'parameters' => [
                                    'order' => $order->id
                                ]
                            ],
                            'download' => [
                                'name'       => 'grp.org.shops.show.ordering.order.uploads.templates',
                                'parameters' => [
                                    'organisation' => $order->organisation->slug,
                                    'shop'         => $order->shop->slug,
                                    'order'        => $order->slug
                                ]
                            ],
                        ],
                    ]
                ],

                OrderTabsEnum::TRANSACTIONS->value => $this->tab == OrderTabsEnum::TRANSACTIONS->value ?
                    fn () => TransactionsResource::collection(IndexTransactions::run(parent: $order, prefix: OrderTabsEnum::TRANSACTIONS->value))
                    : Inertia::lazy(fn () => TransactionsResource::collection(IndexTransactions::run(parent: $order, prefix: OrderTabsEnum::TRANSACTIONS->value))),

                OrderTabsEnum::INVOICES->value => $this->tab == OrderTabsEnum::INVOICES->value ?
                    fn () => InvoicesResource::collection(IndexInvoicesInOrder::run(order: $order, prefix: OrderTabsEnum::TRANSACTIONS->value))
                    : Inertia::lazy(fn () => InvoicesResource::collection(IndexInvoicesInOrder::run(order: $order, prefix: OrderTabsEnum::TRANSACTIONS->value))),

                OrderTabsEnum::DELIVERY_NOTES->value => $this->tab == OrderTabsEnum::DELIVERY_NOTES->value ?
                    fn () => DeliveryNotesResource::collection(IndexDeliveryNotes::run(parent: $order, prefix: OrderTabsEnum::DELIVERY_NOTES->value))
                    : Inertia::lazy(fn () => DeliveryNotesResource::collection(IndexDeliveryNotes::run(parent: $order, prefix: OrderTabsEnum::DELIVERY_NOTES->value))),

                OrderTabsEnum::ATTACHMENTS->value => $this->tab == OrderTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run(parent: $order, prefix: OrderTabsEnum::DELIVERY_NOTES->value))
                    : Inertia::lazy(fn () => AttachmentsResource::collection(IndexAttachments::run(parent: $order, prefix: OrderTabsEnum::DELIVERY_NOTES->value))),

                OrderTabsEnum::PAYMENTS->value => $this->tab == OrderTabsEnum::PAYMENTS->value ?
                    fn () => PaymentsResource::collection(IndexPayments::run(parent: $order, prefix: OrderTabsEnum::PAYMENTS->value))
                    : Inertia::lazy(fn () => PaymentsResource::collection(IndexPayments::run(parent: $order, prefix: OrderTabsEnum::PAYMENTS->value))),

            ]
        )
            ->table(
                IndexTransactions::make()->tableStructure(
                    parent: $order,
                    tableRows: $nonProductItems,
                    prefix: OrderTabsEnum::TRANSACTIONS->value
                )
            )
            ->table(
                IndexInvoicesInOrder::make()->tableStructure(
                    order: $order,
                    prefix: OrderTabsEnum::INVOICES->value
                )
            )
            ->table(
                IndexAttachments::make()->tableStructure(
                    prefix: OrderTabsEnum::ATTACHMENTS->value
                )
            )
            ->table(
                IndexPayments::make()->tableStructure(
                    parent: $order,
                    prefix: OrderTabsEnum::PAYMENTS->value
                )
            )
            ->table(
                IndexDeliveryNotes::make()->tableStructure(
                    parent: $order,
                    prefix: OrderTabsEnum::DELIVERY_NOTES->value
                )
            );
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->fillFromRequest($request);

        $this->set('canEdit', $request->user()->authTo('hr.edit'));
        $this->set('canViewUsers', $request->user()->authTo('users.view'));
    }

    public function jsonResponse(Order $order): OrderResource
    {
        return new OrderResource($order);
    }

    public function getBreadcrumbs(Order $order, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Order $order, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Orders')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $order->reference,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.ordering.orders.show',
            'grp.org.shops.show.ordering.orders.edit',
            'grp.org.shops.show.ordering.orders.show.delivery-note'
            => array_merge(
                (new ShowShop())->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $order,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.ordering.orders.index',
                            'parameters' => Arr::except($routeParameters, ['order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.ordering.orders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.crm.customers.show.orders.show',
            => array_merge(
                (new ShowCustomer())->getBreadcrumbs('grp.org.shops.show.crm.customers.show', $routeParameters),
                $headCrumb(
                    $order,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.orders.index',
                            'parameters' => Arr::except($routeParameters, ['order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.orders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.crm.customers.show.customer_clients.orders.show'
            => array_merge(
                ShowCustomerClient::make()->getBreadcrumbs($order->customer, 'grp.org.shops.show.crm.customers.show.customer_clients.show', $routeParameters),
                $headCrumb(
                    $order,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_clients.orders.index',
                            'parameters' => Arr::except($routeParameters, ['order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_clients.orders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.ordering.purges.order'
            => array_merge(
                (new ShowPurge())->getBreadcrumbs($this->parent, 'grp.org.shops.show.ordering.purges.order', $routeParameters),
                $headCrumb(
                    $order,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.ordering.purges.show',
                            'parameters' => Arr::except($routeParameters, ['order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.ordering.purges.order',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.orders.show'
            => array_merge(
                (new ShowCustomerSalesChannel())->getBreadcrumbs($order->customerSalesChannel, 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.orders.index', $routeParameters),
                $headCrumb(
                    $order,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.orders.index',
                            'parameters' => Arr::except($routeParameters, ['order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.orders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.customer_clients.show.orders.show'
            => array_merge(
                (new ShowCustomerClient())->getBreadcrumbs($this->customerSalesChannel, 'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.customer_clients.show', $routeParameters),
                $headCrumb(
                    $order,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.customer_clients.show.orders.index',
                            'parameters' => Arr::except($routeParameters, ['order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.customer_clients.show.orders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show.orders.show'
            => array_merge(
                (new ShowCustomerClient())->getBreadcrumbs($this->customerSalesChannel, 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show', $routeParameters),
                $headCrumb(
                    $order,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show.orders.index',
                            'parameters' => Arr::except($routeParameters, ['order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show.orders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(Order $order, ActionRequest $request): ?array
    {
        $previous = Order::where('reference', '<', $order->reference)->when(true, function ($query) use ($order, $request) {
            if ($request->route()->getName() == 'shops.show.orders.show') {
                $query->where('orders.shop_id', $order->shop_id);
            }
        })->orderBy('reference', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Order $order, ActionRequest $request): ?array
    {
        $next = Order::where('reference', '>', $order->reference)->when(true, function ($query) use ($order, $request) {
            if ($request->route()->getName() == 'shops.show.orders.show') {
                $query->where('orders.shop_id', $order->shop_id);
            }
        })->orderBy('reference')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Order $order, string $routeName): ?array
    {
        if (!$order) {
            return null;
        }


        return match ($routeName) {
            'grp.org.shops.show.ordering.orders.show' => [
                'label' => $order->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'shop'         => $order->shop->slug,
                        'order'        => $order->slug
                    ]

                ]
            ],
            'grp.org.shops.show.crm.customers.show.orders.show' => [
                'label' => $order->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'shop'         => $order->shop->slug,
                        'customer'     => $this->parent->slug,
                        'order'        => $order->slug
                    ]

                ]
            ],
            'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.orders.show' => [
                'label' => $order->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'shop'         => $order->shop->slug,
                        'customer'     => $this->parent->customer->slug,
                        'platform'     => $this->parent->platform->slug,
                        'order'        => $order->slug
                    ]

                ]
            ],
            'grp.org.shops.show.ordering.purges.order' => [
                'label' => $order->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'shop'         => $order->shop->slug,
                        'purge'        => $this->parent->slug,
                        'order'        => $order->slug
                    ]

                ]
            ],
            'grp.org.shops.show.crm.customers.show.customer_clients.orders.show' => [
                'label' => $order->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'   => $this->organisation->slug,
                        'shop'           => $order->shop->slug,
                        'customer'       => $this->parent->customer->slug,
                        'customerClient' => $this->parent->ulid,
                        'order'          => $order->slug
                    ]

                ]
            ],
            default => null
        };
    }
}
