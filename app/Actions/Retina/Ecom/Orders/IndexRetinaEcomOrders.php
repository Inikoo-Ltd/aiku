<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Aug 2025 14:56:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ecom\Orders;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\Helpers\CurrencyResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Closure;

class IndexRetinaEcomOrders extends RetinaAction
{
    public function handle(Customer $customer, $prefix = null): LengthAwarePaginator
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
        $query->where('orders.customer_id', $customer->id);
        $query->whereNotIn('orders.state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED]);

        $query->leftJoin('order_stats', 'orders.id', '=', 'order_stats.order_id');

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
        );

        return $query->defaultSort('-orders.date')
            ->allowedSorts(['state', 'reference', 'date', 'number_item_transactions', 'total_amount', 'payment_amount'])
            ->allowedFilters([$globalSearch])
            ->withRetinaPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }



    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($this->customer);
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
                'title' => __("You don't have any orders yet"),
                'count' => 0
            ];
            $table->withLabelRecord([__('order'), __('orders')]);
            $table->withGlobalSearch()
                ->withEmptyState($emptyStateData);

            $table->column(key: 'state', label: __('Status'), sortable: true, type: 'icon');
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true, type: 'date');
            $table->column(key: 'number_item_transactions', label: __('items'), canBeHidden: false, sortable: true);
            $table->column(key: 'total_amount', label: __('total'), canBeHidden: false, sortable: true, align: "right");
        };
    }


    public function htmlResponse(LengthAwarePaginator $orders): Response
    {
        $actions = [];
        return Inertia::render(
            'Ecom/RetinaEcomOrders',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Orders'),
                'pageHead'    => [
                    'icon'       => 'fal fa-shopping-cart',
                    'title'      => __('Orders'),
                    'actions'    => $actions
                ],
                'currency'              => CurrencyResource::make($this->shop->currency)->getArray(),
                'data'                  => $orders,
            ]
        )->table($this->tableStructure());
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
                                'name' => 'retina.ecom.orders.index'
                            ],
                            'label' => __('Orders'),
                        ]
                    ]
                ]
            );
    }
}
