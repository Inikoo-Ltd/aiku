<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 14 Oct 2024 14:05:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Retina\Platform\ShowRetinaCustomerSalesChannelDashboard;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithPlatformStatusCheck;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\Fulfilment\RetinaDropshippingOrdersInCustomerSalesChannelResources;
use App\Http\Resources\Helpers\CurrencyResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Closure;

class IndexRetinaDropshippingOrders extends RetinaAction
{
    use WithPlatformStatusCheck;

    private CustomerSalesChannel $customerSalesChannel;

    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('orders.reference', $value)
                    ->orWhereWith('orders.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Order::class);
        $query->where('orders.customer_sales_channel_id', $customerSalesChannel->id);
        $query->whereNotIn('orders.state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED]);

        $query->leftJoin('order_stats', 'orders.id', '=', 'order_stats.order_id');
        $query->leftJoin('customer_clients', 'customer_clients.id', '=', 'orders.customer_client_id');

        $query->select(
            'orders.id',
            'orders.slug',
            'orders.reference',
            'orders.state',
            'orders.customer_reference',
            'order_stats.number_item_transactions as number_item_transactions',
            'orders.date',
            'orders.total_amount',
            'orders.payment_amount',
            'customer_clients.name as client_name',
            'customer_clients.ulid as client_ulid',
        );

        return $query->defaultSort('-orders.date')
            ->allowedSorts(['state', 'reference', 'client_name', 'date', 'number_item_transactions', 'total_amount', 'payment_amount'])
            ->allowedFilters([$globalSearch])
            ->withRetinaPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route()->parameter('customerSalesChannel');
        if ($customerSalesChannel->customer_id == $this->customer->id) {
            return true;
        }

        return false;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request);

        return $this->handle($customerSalesChannel);
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $emptyStateData = [
                'icons' => ['fal fa-pallet'],
                'title' => __("This channel doesn't have any orders yet"),
                'count' => 0
            ];
            $table->withLabelRecord([__('order'), __('orders')]);
            $table->withGlobalSearch()
                ->withEmptyState($emptyStateData);

            $table->column(key: 'state', label: __('Status'), sortable: true, type: 'icon');
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'client_name', label: __('client'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true, type: 'date');
            $table->column(key: 'number_item_transactions', label: __('items'), canBeHidden: false, sortable: true);
            $table->column(key: 'total_amount', label: __('total'), canBeHidden: false, sortable: true, align: "right");
        };
    }


    public function htmlResponse(LengthAwarePaginator $orders): Response
    {
        $actions = [];

        return Inertia::render(
            'Dropshipping/RetinaOrders',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->customerSalesChannel),
                'title'       => __('Orders'),
                'pageHead'    => [
                    'icon'       => 'fal fa-shopping-cart',
                    'title'      => __('Orders'),
                    'afterTitle' => [
                        'label' => '@'.$this->customerSalesChannel->name,
                    ],
                    'actions'    => $actions
                ],

                'is_platform_connected' => $this->checkStatus($this->customerSalesChannel) === 'connected',
                'currency'              => CurrencyResource::make($this->shop->currency)->getArray(),
                'data'                  => RetinaDropshippingOrdersInCustomerSalesChannelResources::collection($orders)
            ]
        )->table($this->tableStructure());
    }


    public function getBreadcrumbs(CustomerSalesChannel $customerSalesChannel): array
    {
        return
            array_merge(
                ShowRetinaCustomerSalesChannelDashboard::make()->getBreadcrumbs($customerSalesChannel),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'retina.dropshipping.customer_sales_channels.orders.index',
                                'parameters' => [
                                    $customerSalesChannel->slug
                                ]
                            ],
                            'label' => __('Orders'),
                        ]
                    ]
                ]
            );
    }
}
