<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Basket\UI;

use App\Actions\Retina\Platform\ShowRetinaCustomerSalesChannelDashboard;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Http\Resources\Ordering\RetinaOrdersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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
                    ->orWhereWith('customer_clients.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Order::class);
        $query->where('orders.customer_sales_channel_id', $customerSalesChannel->id);
        $query->leftJoin('customer_clients', 'orders.customer_client_id', '=', 'customer_clients.id');
        $query->leftJoin('transactions', 'orders.id', '=', 'transactions.order_id');


        $query->where(function ($q) {
            $q->whereIn('transactions.model_type', ['Product', 'Service'])
                ->orWhereNull('transactions.model_type');
        });
        $query->where('orders.platform_id', $customerSalesChannel->platform_id);
        $query->where('orders.state', OrderStateEnum::CREATING);

        return $query->defaultSort('orders.id')
            ->select([
                'orders.id',
                'orders.reference',
                'orders.slug',
                'orders.customer_client_id',
                'orders.total_amount',
                'customer_clients.name as customer_client_name',
                'customer_clients.ulid as customer_client_ulid',
                'orders.created_at',
                DB::raw('COUNT(transactions.id) as items'),
            ])
            ->groupBy(
                'orders.id',
                'orders.reference',
                'orders.slug',
                'orders.customer_client_id',
                'orders.total_amount',
                'customer_clients.name',
                'customer_clients.ulid',
                'orders.created_at'
            )
            ->allowedSorts(['reference', 'customer_client_name', 'total_amount', 'items', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->customerSalesChannel->customer_id == $this->customer->id) {
            return true;
        }
        return false;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->platform = $customerSalesChannel->platform;
        $this->initialisation($request);

        return $this->handle($customerSalesChannel, 'orders');
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
                    'afterTitle'    => [
                        'label'     => '@'.$this->customerSalesChannel->name,
                    ],
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => ProductTabsEnum::navigation()
                ],

                'currency' => CurrencyResource::make($this->customer->shop->currency)->toArray(request()),

                'orders' => RetinaOrdersResource::collection($orders)
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

            $table->withLabelRecord([__('basket'), __('baskets')]);
            $table->withGlobalSearch()
                ->withModelOperations($modelOperations);

            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'customer_client_name', label: __('customer client'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'total_amount', label: __('total'), canBeHidden: false, align: 'right', sortable: true);
            $table->column(key: 'items', label: __('items'), canBeHidden: false, sortable: true);
            $table->column(key: 'created_at', label: __('date'), canBeHidden: false, type: 'date', sortable: true);
        };
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
