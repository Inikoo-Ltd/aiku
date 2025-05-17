<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 14 Oct 2024 14:05:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\Fulfilment\RetinaDropshippingOrdersResources;
use App\Http\Resources\Helpers\CurrencyResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaDropshippingOrders extends RetinaAction
{
    public function handle(Customer $customer, ?Platform $platform = null, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customer_clients.name', $value)
                    ->orWhereWith('orders.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Order::class);
        $query->leftJoin('order_stats', 'order_stats.order_id', '=', 'orders.id');
        $query->leftJoin('customer_clients', 'customer_clients.id', '=', 'orders.customer_client_id');

        if ($platform) {
            $query->where('orders.platform_id', $platform->id);
        }
        $query->leftJoin('platforms', 'platforms.id', '=', 'orders.platform_id');

        $query->where('orders.customer_id', $customer->id);


        $query->defaultSort('-orders.date');
        $query->select([
            'orders.id',
            'orders.date',
            'orders.reference',
            'orders.slug',
            'orders.state',
            'orders.total_amount',
            'platforms.name as platform_name',
            'number_item_transactions',
            'customer_clients.name as client_name',
        ]);

        return $query->defaultSort('orders.id')
            ->allowedSorts([
                'reference',
                'date',
                'state',
                'number_item_transactions',
                'total_amount',
                'client_name',
                'platform_name'
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($this->customer);
    }

    public function htmlResponse(LengthAwarePaginator $orders): Response
    {
        return Inertia::render(
            'Dropshipping/RetinaOrders',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Orders'),
                'pageHead'    => [
                    'title' => __('Orders'),
                    'icon'  => 'fal fa-shopping-cart',
                ],


                'currency' => CurrencyResource::make($this->shop->currency)->getArray(),

                'orders' => RetinaDropshippingOrdersResources::collection($orders)
            ]
        )->table($this->tableStructure(''));
    }

    public function tableStructure(?Platform $platform = null, $prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations, $platform) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $emptyStateData = [
                'icons' => ['fal fa-pallet'],
                'title' => __("There are no orders yet"),
                'count' => 0
            ];

            $table->withGlobalSearch()
                ->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);

            $table->column(key: 'state', label: __('Status'), sortable: true, type: 'icon');

            if (!$platform) {
                $table->column(key: 'platform_name', label: __('Channel'), sortable: true);
            } elseif ($platform->type == PlatformTypeEnum::SHOPIFY) {
                $table->column(key: 'shopify_order_id', label: __('shopify order id'), canBeHidden: false, searchable: true);
            } elseif ($platform->type == PlatformTypeEnum::TIKTOK) {
                $table->column(key: 'tiktok_order_id', label: __('tiktok order id'), canBeHidden: false, searchable: true);
            }


            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'client_name', label: __('customer'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true, type: 'date');
            $table->column(key: 'number_item_transactions', label: __('items'), canBeHidden: false, sortable: true);
            $table->column(key: 'total_amount', label: __('total'), canBeHidden: false, sortable: true, align: "right");
        };
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.dropshipping.orders.index'
                            ],
                            'label' => __('Orders'),
                        ]
                    ]
                ]
            );
    }
}
