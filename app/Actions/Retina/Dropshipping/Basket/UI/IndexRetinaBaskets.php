<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Basket\UI;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Http\Resources\Ordering\OrdersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaBaskets extends RetinaAction
{
    private CustomerSalesChannel $customerSalesChannel;
    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null): \Illuminate\Pagination\LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('orders.reference', $value)
                    ->orWhereWith('orders.customer_reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Order::class);
        $query->where('orders.customer_sales_channel_id', $customerSalesChannel->id);
        $query->where('orders.platform_id', $customerSalesChannel->platform_id);
        $query->where('orders.state', OrderStateEnum::CREATING);

        return $query->defaultSort('id')
            ->allowedSorts(['id'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisationFromPlatform($customerSalesChannel->platform, $request);

        return $this->handle($customerSalesChannel);
    }

    public function htmlResponse(LengthAwarePaginator $orders): Response
    {

        $title = __('Baskets');

        return Inertia::render(
            'Dropshipping/RetinaOrders',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->customerSalesChannel),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => 'fal fa-shopping-basket',
                    'afterTitle' => [
                        'label' => ' @'.$this->platform->name
                    ],
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => ProductTabsEnum::navigation()
                ],

                'currency' => CurrencyResource::make($this->customer->shop->currency)->toArray(request()),

                'orders' => OrdersResource::collection($orders)
            ]
        )->table($this->tableStructure('orders'));
    }

    public function tableStructure($prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $emptyStateData = [
                'title' => __("You dont have any baskets open"),
                'count' => 0
            ];

            $table->withGlobalSearch()
                ->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);

            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, searchable: true);

            $table->column(key: 'total_amount', label: __('total'), canBeHidden: false, searchable: true);
        };
    }

    public function getBreadcrumbs(CustomerSalesChannel $customerSalesChannel): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.dropshipping.customer_sales_channels.basket.index',
                                'parameters' => [
                                    $customerSalesChannel->slug
                                ]
                            ],
                            'label'  => __('Baskets'),
                        ]
                    ]
                ]
            );
    }
}
