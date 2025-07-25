<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\CRM\Customer\UI\ShowCustomerClient;
use App\Actions\CRM\Customer\UI\WithCustomerSubNavigation;
use App\Actions\Ordering\Order\WithOrdersSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\UI\Ordering\OrdersBacklogTabsEnum;
use App\Enums\UI\Ordering\OrdersTabsEnum;
use App\Http\Resources\Ordering\OrdersResource;
use App\Http\Resources\Sales\OrderResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrders extends OrgAction
{
    use WithOrderingAuthorisation;
    use WithCustomerSubNavigation;
    use WithOrdersSubNavigation;

    private Shop|Customer|CustomerClient $parent;
    private CustomerSalesChannel $customerSalesChannel;

    private string $bucket;

    protected function getElementGroups(Shop|Customer|CustomerClient $parent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    OrderStateEnum::labels(),
                    OrderStateEnum::count($parent)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('orders.state', $elements);
                }
            ],


        ];
    }

    public function handle(Shop|Customer|CustomerClient $parent, $prefix = null, $bucket = null): LengthAwarePaginator
    {
        if ($bucket) {
            $this->bucket = $bucket;
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('orders.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $query = QueryBuilder::for(Order::class);

        if (class_basename($parent) == 'Shop') {
            $query->where('orders.shop_id', $parent->id);
            $shop = $parent;
        } elseif (class_basename($parent) == 'Customer') {
            $query->where('orders.customer_id', $parent->id);
            $shop = $parent->shop;
        } else {
            $query->where('orders.customer_client_id', $parent->id);
            $shop = $parent->shop;
        }

        $query->leftJoin('customers', 'orders.customer_id', '=', 'customers.id');
        $query->leftJoin('customer_clients', 'orders.customer_client_id', '=', 'customer_clients.id');

        //        $query->leftJoin('model_has_payments', function ($join) {
        //            $join->on('orders.id', '=', 'model_has_payments.model_id')
        //                ->where('model_has_payments.model_type', '=', 'Order');
        //        })
        //  $query->leftJoin('payments', 'model_has_payments.payment_id', '=', 'payments.id');

        $query->leftJoin('currencies', 'orders.currency_id', '=', 'currencies.id');
        $query->leftJoin('organisations', 'orders.organisation_id', '=', 'organisations.id');
        $query->leftJoin('shops', 'orders.shop_id', '=', 'shops.id');

        if ($this->bucket == 'creating' || $this->bucket == OrdersBacklogTabsEnum::IN_BASKET->value) {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING) { // tmp stuff until we migrate from aurora
                $query->whereNull('orders.source_id');
            }
            $query->where('orders.state', OrderStateEnum::CREATING);
        } elseif ($this->bucket == OrdersBacklogTabsEnum::SUBMITTED_PAID->value) {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING) { // tmp stuff until we migrate from aurora
                $query->whereNull('orders.source_id');
            }


            $query->where('orders.state', OrderStateEnum::SUBMITTED->value)
               ->where('orders.pay_status', OrderPayStatusEnum::PAID);
        } elseif ($this->bucket == OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value) {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING) { // tmp stuff until we migrate from aurora
                $query->whereNull('orders.source_id');
            }


            $query->where('orders.state', OrderStateEnum::SUBMITTED->value)
                ->where('orders.pay_status', '!=', OrderPayStatusEnum::PAID);
        } elseif ($this->bucket == OrdersBacklogTabsEnum::PICKING->value) {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING) { // tmp stuff until we migrate from aurora
                $query->whereNull('orders.source_id');
            }
            $query->where('orders.state', OrderStateEnum::HANDLING);
        } elseif ($this->bucket == OrdersBacklogTabsEnum::BLOCKED->value) {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING) { // tmp stuff until we migrate from aurora
                $query->whereNull('orders.source_id');
            }
            $query->where('orders.state', OrderStateEnum::HANDLING_BLOCKED);
        } elseif ($this->bucket == OrdersBacklogTabsEnum::PACKED->value) {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING) { // tmp stuff until we migrate from aurora
                $query->whereNull('orders.source_id');
            }
            $query->where('orders.state', OrderStateEnum::PACKED);
        } elseif ($this->bucket == OrdersBacklogTabsEnum::PACKED_DONE->value) {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING) { // tmp stuff until we migrate from aurora
                $query->whereNull('orders.source_id');
            }
            $query->where('orders.state', OrderStateEnum::FINALISED);
        } elseif ($this->bucket == OrdersBacklogTabsEnum::DISPATCHED_TODAY->value) {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING) { // tmp stuff until we migrate from aurora
                $query->whereNull('orders.source_id');
            }
            $query->whereDate('dispatched_at', Carbon::today());
        } elseif ($this->bucket == 'submitted') {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING) { // tmp stuff until we migrate from aurora
                $query->whereNull('orders.source_id');
            }
            $query->where('orders.state', OrderStateEnum::SUBMITTED);
        } elseif ($this->bucket == 'in_warehouse') {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING) { // tmp stuff until we migrate from aurora
                $query->whereNull('orders.source_id');
            }
            $query->where('orders.state', OrderStateEnum::IN_WAREHOUSE);
        } elseif ($this->bucket == 'handling') {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING) { // tmp stuff until we migrate from aurora
                $query->whereNull('orders.source_id');
            }

            $query->where('orders.state', OrderStateEnum::HANDLING);
        } elseif ($this->bucket == 'handling_blocked') {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING) { // tmp stuff until we migrate from aurora
                $query->whereNull('orders.source_id');
            }
            $query->where('orders.state', OrderStateEnum::HANDLING_BLOCKED);
        } elseif ($this->bucket == 'packed') {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING) { // tmp stuff until we migrate from aurora
                $query->whereNull('orders.source_id');
            }
            $query->where('orders.state', OrderStateEnum::PACKED);
        } elseif ($this->bucket == 'finalised') {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING) { // tmp stuff until we migrate from aurora
                $query->whereNull('orders.source_id');
            }
            $query->where('orders.state', OrderStateEnum::FINALISED);
        } elseif ($this->bucket == 'dispatched') {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING) { // tmp stuff until we migrate from aurora
                $query->whereNull('orders.source_id');
            }
            $query->where('orders.state', OrderStateEnum::DISPATCHED);
        } elseif ($this->bucket == 'cancelled') {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING) { // tmp stuff until we migrate from aurora
                $query->whereNull('orders.source_id');
            }
            $query->where('orders.state', OrderStateEnum::CANCELLED);
        } elseif ($this->bucket == 'dispatched_today') {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING) { // tmp stuff until we migrate from aurora
                $query->whereNull('orders.source_id');
            }
            $query->where('orders.state', OrderStateEnum::DISPATCHED)
                ->where('dispatched_at', Carbon::today());
        } elseif ($this->bucket == 'all') {
            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $query->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }


        return $query->defaultSort('-orders.date')
        ->select([
            'orders.id',
            'orders.slug',
            'orders.reference',
            'orders.date',
            'orders.state',
            'orders.created_at',
            'orders.updated_at',
            'orders.slug',
            'orders.net_amount',
            'orders.total_amount',
            'customers.name as customer_name',
            'customers.slug as customer_slug',
            'customer_clients.name as client_name',
            'customer_clients.ulid as client_ulid',
            //'payments.state as payment_state',
            //'payments.status as payment_status',
            'currencies.code as currency_code',
            'currencies.id as currency_id',
            'shops.name as shop_name',
            'shops.slug as shop_slug',
            'organisations.name as organisation_name',
            'organisations.slug as organisation_slug',
            'customers.slug as customer_slug',
            'customers.name as customer_name',
        ])
            ->leftJoin('order_stats', 'orders.id', 'order_stats.order_id')
            ->allowedSorts(['id', 'reference', 'date']) // Ensure `id` is the first sort column
            ->withBetweenDates(['date'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop|Customer|CustomerClient $parent, $prefix = null, $bucket = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix, $bucket) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($prefix) {
                InertiaTable::updateQueryBuilderParameters($prefix);
            }


            $noResults = __("No orders found");
            if ($parent instanceof Customer) {
                $stats     = $parent->stats;
                $noResults = __("Customer has no orders");
            } elseif ($parent instanceof CustomerClient) {
                $stats     = $parent->stats;
                $noResults = __("This customer client hasn't place any orders");
            } else {
                $stats = $parent->orderingStats;
            }

            $table->betweenDates(['date']);

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $stats->number_orders ?? 0
                    ]
                );

            if ($bucket == 'all') {
                foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }

            $table->column(key: 'state', label: '', canBeHidden: false, searchable: true, type: 'icon');
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true, type: 'date');
            if ($parent instanceof Shop) {
                $table->column(key: 'customer_name', label: __('customer'), canBeHidden: false, searchable: true);
            }
            $table->column(key: 'payment_status', label: __('payment'), canBeHidden: false, searchable: true);
            $table->column(key: 'net_amount', label: __('net'), canBeHidden: false, searchable: true, type: 'currency');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Customer || $this->parent instanceof CustomerClient) {
            $this->canEdit = $request->user()->authTo("crm.{$this->shop->id}.view");

            return $request->user()->authTo(["crm.{$this->shop->id}.view", "accounting.{$this->shop->organisation_id}.view"]);
        }

        $this->canEdit = $request->user()->authTo("orders.{$this->shop->id}.edit");

        return $request->user()->authTo(["orders.{$this->shop->id}.view", "accounting.{$this->shop->organisation_id}.view"]);
    }

    public function jsonResponse(LengthAwarePaginator $orders): AnonymousResourceCollection
    {
        return OrderResource::collection($orders);
    }

    public function htmlResponse(LengthAwarePaginator $orders, ActionRequest $request): Response
    {
        $navigation    = OrdersTabsEnum::navigation();
        $subNavigation = null;
        if ($this->parent instanceof CustomerClient) {
            unset($navigation[OrdersTabsEnum::STATS->value]);
            $subNavigation = $this->getCustomerClientSubNavigation($this->parent, $this->customerSalesChannel);
        } elseif ($this->parent instanceof Customer) {
            if ($this->parent->is_dropshipping) {
                $subNavigation = $this->getCustomerDropshippingSubNavigation($this->parent, $request);
            } else {
                $subNavigation = $this->getCustomerSubNavigation($this->parent, $request);
            }
        } elseif ($this->parent instanceof Shop) {
            $subNavigation = $this->getOrdersNavigation($this->parent);
        }
        $title      = __('Orders');
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-shopping-cart'],
            'title' => __('orders')
        ];
        $afterTitle = null;
        $iconRight  = null;
        $actions    = null;

        if ($this->parent instanceof CustomerClient) {
            $title      = $this->parent->name;
            $model      = __('customer client');
            $icon       = [
                'icon'  => ['fal', 'fa-folder'],
                'title' => __('customer client')
            ];
            $iconRight  = [
                'icon' => 'fal fa-shopping-cart',
            ];
            $afterTitle = [
                'label' => __('Orders')
            ];
            $actions    = [
                [
                    'type'        => 'button',
                    'style'       => 'create',
                    'label'       => 'Add order',
                    'key'         => 'add_order',
                    'fullLoading' => true,
                    'route'       => [
                        'method'     => 'post',
                        'name'       => 'grp.models.customer_client.order.store',
                        'parameters' => [
                            'customerClient' => $this->parent->id
                        ]
                    ],
                ],
            ];
        } elseif ($this->parent instanceof Customer) {
            $title = $this->parent->name;

            $icon       = [
                'icon'  => ['fal', 'fa-user'],
                'title' => __('customer')
            ];
            $iconRight  = [
                'icon' => 'fal fa-shopping-cart',
            ];
            $afterTitle = [
                'label' => __('Orders')
            ];

            if ($this->shop->type == ShopTypeEnum::B2B) {
                $actions = [
                    [
                        'type'        => 'button',
                        'style'       => 'create',
                        'label'       => 'Add order',
                        'key'         => 'add_order',
                        'fullLoading' => true,
                        'route'       => [
                            'method'     => 'post',
                            'name'       => 'grp.models.customer.order.store',
                            'parameters' => [
                                'customer' => $this->parent->id
                            ]
                        ]
                    ],
                ];
            }
        }

        if ($this->parent instanceof Shop) {
            $shop = $this->parent;
        } else {
            $shop = $this->parent->shop;
        }

        return Inertia::render(
            'Ordering/Orders',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('orders'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
                ],
                'data'        => OrderResource::collection($orders),

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],

                OrdersTabsEnum::STATS->value => $this->tab == OrdersTabsEnum::STATS->value ?
                    fn () => GetOrderStats::run($shop)
                    : Inertia::lazy(fn () => GetOrderStats::run($shop)),

                OrdersTabsEnum::ORDERS->value => $this->tab == OrdersTabsEnum::ORDERS->value ?
                    fn () => OrdersResource::collection($orders)
                    : Inertia::lazy(fn () => OrdersResource::collection($orders)),
            ]
        )->table($this->tableStructure($this->parent, OrdersTabsEnum::ORDERS->value, $this->bucket));
    }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(OrdersTabsEnum::values());

        return $this->handle(parent: $shop, prefix: OrdersTabsEnum::ORDERS->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inCustomer(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->parent = $customer;
        $this->initialisationFromShop($shop, $request)->withTab(OrdersTabsEnum::values());

        return $this->handle(parent: $customer, prefix: OrdersTabsEnum::ORDERS->value);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomerClient(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, CustomerSalesChannel $customerSalesChannel, CustomerClient $customerClient, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket               = 'all';
        $this->parent               = $customerClient;
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(OrdersTabsEnum::values());

        return $this->handle(parent: $customerClient, prefix: OrdersTabsEnum::ORDERS->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inCustomerClient(Organisation $organisation, Shop $shop, Customer $customer, CustomerSalesChannel $customerSalesChannel, CustomerClient $customerClient, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket               = 'all';
        $this->parent               = $customerClient;
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisationFromShop($shop, $request)->withTab(OrdersTabsEnum::values());

        return $this->handle(parent: $customerClient, prefix: OrdersTabsEnum::ORDERS->value);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Orders'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.ordering.orders.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.ordering.orders.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.shops.show.crm.customers.show.orders.index' =>
            array_merge(
                ShowCustomer::make()->getBreadcrumbs('grp.org.shops.show.crm.customers.show', $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.crm.customers.show.orders.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show.orders.index' =>
            array_merge(
                ShowCustomerClient::make()->getBreadcrumbs($this->customerSalesChannel, 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show', $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show.orders.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.customer_clients.show.orders.index' =>
            array_merge(
                ShowCustomerClient::make()->getBreadcrumbs($this->customerSalesChannel, 'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.customer_clients.show', $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.customer_clients.show.orders.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
