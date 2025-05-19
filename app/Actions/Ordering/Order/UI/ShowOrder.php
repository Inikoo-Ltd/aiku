<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\Accounting\Invoice\UI\IndexInvoices;
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
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\UI\Ordering\OrderTabsEnum;
use App\Http\Resources\Accounting\InvoicesResource;
use App\Http\Resources\Dispatching\DeliveryNotesResource;
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
    private CustomerSalesChannel $customerHasPlatform;

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
    public function inPlatformInCustomer(Organisation $organisation, Shop $shop, Customer $customer, Platform $platform, Order $order, ActionRequest $request): Order
    {
        $customerHasPlatform = CustomerSalesChannel::where('customer_id', $customer->id)->where('platform_id', $platform->id)->first();
        $this->parent        = $customerHasPlatform;
        $this->initialisationFromShop($shop, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inCustomerClient(Organisation $organisation, Shop $shop, Customer $customer, Platform $platform, CustomerClient $customerClient, Order $order, ActionRequest $request): Order
    {
        $customerHasPlatform       = CustomerSalesChannel::where('customer_id', $customerClient->customer_id)->where('platform_id', $platform->id)->first();
        $this->parent              = $customerClient;
        $this->customerHasPlatform = $customerHasPlatform;
        $this->initialisationFromShop($shop, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomerClient(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, CustomerClient $customerClient, CustomerSalesChannel $customerHasPlatform, Order $order, ActionRequest $request): Order
    {
        $this->parent              = $customerClient;
        $this->customerHasPlatform = $customerHasPlatform;
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
        ];
    }


    public function htmlResponse(Order $order, ActionRequest $request): Response
    {
        $finalTimeline = $this->getOrderTimeline($order);

        $nonProductItems = NonProductItemsResource::collection(IndexNonProductItems::run($order));

        $actions = GetOrderActions::run($order, $this->canEdit);

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
                    'name'       => 'grp.org.warehouses.show.dispatching.delivery-notes.pdf',
                    'parameters' => [
                        'organisation' => $order->organisation->slug,
                        'warehouse'    => $firstDeliveryNote->warehouse->slug,
                        'deliveryNote' => $firstDeliveryNote->slug,
                    ],
                ]
            ];

            $deliveryNoteResource = DeliveryNotesResource::make($firstDeliveryNote);
        }

        $platform  = $order->platform;
        if (!$platform) {
            $platform = Platform::where('type', PlatformTypeEnum::MANUAL)->first();
        }

        $readonly = true;
        if ($platform->type == PlatformTypeEnum::MANUAL) {
            $readonly = false;
        }
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
                    'title'   => $order->reference,
                    'model'   => __('Order'),
                    'icon'    => [
                        'icon'  => 'fal fa-shopping-cart',
                        'title' => __('customer client')
                    ],
                    'actions' => $actions,
                    'platform' => $platform ? [
                                            'icon'  => $platform->imageSources(24, 24),
                                            'title' => $platform->name,
                                            ] : null,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => OrderTabsEnum::navigation()
                ],
                'routes'      => [
                    'updateOrderRoute' => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.order.update',
                        'parameters' => [
                            'order' => $order->id,
                        ]
                    ],
                    'products_list'    => [
                        'name'       => 'grp.json.order.products',
                        'parameters' => [
                            'order' => $order->id
                        ]
                    ],
                    'delivery_note'    => $deliveryNoteRoute
                ],

                'notes'     => $this->getOrderNotes($order),
                'timelines' => $finalTimeline,
                'readonly' => $readonly,
                'address_management' => GetOrderAddressManagement::run(order: $order),

                'box_stats'     => $this->getOrderBoxStats($order),
                'currency'      => CurrencyResource::make($order->currency)->toArray(request()),
                'data'          => OrderResource::make($order),
                'delivery_note' => $deliveryNoteResource,

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
                    'title' => [
                        'label' => __('Upload product'),
                        'information' => __('The list of column file: code, quantity')
                    ],
                    'progressDescription'   => __('Adding Products'),
                    'preview_template'    => [
                        'header' => ['code', 'quantity'],
                        'rows' => [
                            [
                                'code' => 'product-001',
                                'quantity' => '1'
                            ]
                        ]
                    ],
                    'upload_spreadsheet'    => [
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
            ->table(
                IndexInvoices::make()->tableStructure(
                    parent: $order,
                    prefix: OrderTabsEnum::INVOICES->value
                )
            )
            ->table(
                IndexAttachments::make()->tableStructure(
                    prefix: OrderTabsEnum::ATTACHMENTS->value
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
            'grp.org.shops.show.crm.customers.show.customer-clients.orders.show'
            => array_merge(
                ShowCustomerClient::make()->getBreadcrumbs($order->customer, 'grp.org.shops.show.crm.customers.show.customer-clients.show', $routeParameters),
                $headCrumb(
                    $order,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer-clients.orders.index',
                            'parameters' => Arr::except($routeParameters, ['order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer-clients.orders.show',
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
            'grp.org.shops.show.crm.customers.show.platforms.show.orders.show'
            => array_merge(
                (new ShowCustomerSalesChannel())->getBreadcrumbs($this->parent->platform, 'grp.org.shops.show.crm.customers.show.platforms.show.orders.index', $routeParameters),
                $headCrumb(
                    $order,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.platforms.show.orders.index',
                            'parameters' => Arr::except($routeParameters, ['order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.platforms.show.orders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.fulfilments.show.crm.customers.show.platforms.show.customer-clients.show.orders.show'
            => array_merge(
                (new ShowCustomerClient())->getBreadcrumbs($this->customerHasPlatform, 'grp.org.fulfilments.show.crm.customers.show.platforms.show.customer-clients.show', $routeParameters),
                $headCrumb(
                    $order,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.platforms.show.customer-clients.show.orders.index',
                            'parameters' => Arr::except($routeParameters, ['order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.platforms.show.customer-clients.show.orders.show',
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
            'grp.org.shops.show.crm.customers.show.platforms.show.orders.show' => [
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
            'grp.org.shops.show.crm.customers.show.customer-clients.orders.show' => [
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
