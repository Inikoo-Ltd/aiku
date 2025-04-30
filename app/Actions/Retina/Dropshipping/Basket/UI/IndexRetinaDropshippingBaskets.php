<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Basket\UI;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\Fulfilment\RetinaDropshippingBasketsResources;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Ordering\Transaction;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaDropshippingBaskets extends RetinaAction
{
    public function handle(ShopifyUser|Customer $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('product_name', $value)
                    ->orWhereWith('product_name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Transaction::class);

        if ($parent instanceof Customer) {
            $query->where('customer_id', $parent->id);
        }
        $query->where('state', OrderStateEnum::CREATING);
        $query->where('model_type', Product::class);

        $query->leftJoin('products', 'transactions.model_id', '=', 'products.id');

        $query->defaultSort('products.id');

        return $query->defaultSort('products_id')
            ->select([
                'products.code as product_code',
                'products.name as product_name',
                'products.id as product_id',
                'products.slug as product_slug',
                'transactions.quantity_ordered as quantity',
                'transactions.net_amount as net_amount',
                'transactions.date as date',
            ])
            ->allowedSorts(['products.id'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        $customer = $request->user()->customer;

        return $this->handle($customer);
    }

    public function htmlResponse(LengthAwarePaginator $orders): Response
    {
        return Inertia::render(
            'Dropshipping/Orders',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Baskets'),
                'pageHead'    => [
                    'title' => __('Baskets'),
                    'icon'  => 'fal fa-shopping-basket'
                ],

                'products' => RetinaDropshippingBasketsResources::collection($orders)
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
                'icons' => ['fal fa-pallet'],
                'title' => __("No order exist"),
                'count' => 0
            ];

            $table->withGlobalSearch()
                ->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);


            $table->column(key: 'product_code', label: __('Code'), canBeHidden: false, searchable: true);
            $table->column(key: 'product_name', label: __('Name'), canBeHidden: false, searchable: true);
            $table->column(key: 'quantity', label: __('quantity'), canBeHidden: false, searchable: true);
            $table->column(key: 'net_amount', label: __('net amount'), canBeHidden: false, searchable: true);
            $table->column(key: 'date', label: __('date'), canBeHidden: false, searchable: true);
        };
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
                                'name' => 'retina.dropshipping.orders.index'
                            ],
                            'label'  => __('Orders'),
                        ]
                    ]
                ]
            );
    }
}
