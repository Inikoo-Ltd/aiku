<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 25 May 2025 19:07:15 Central Indonesia Time, Plane KL-Shanghai
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Sorts\Sort;

class IndexProducts extends OrgAction
{
    use WithDepartmentSubNavigation;
    use WithFamilySubNavigation;
    use WithCollectionSubNavigation;
    use WithCatalogueAuthorisation;


    public function handle(Shop $shop, $prefix = null, $bucket = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->orderBy('products.state');

        $queryBuilder->leftJoin('asset_sales_intervals', 'products.asset_id', 'asset_sales_intervals.asset_id');
        $queryBuilder->leftJoin('asset_ordering_intervals', 'products.asset_id', 'asset_ordering_intervals.asset_id');
        $queryBuilder->where('products.is_main', true);
        $queryBuilder->where('products.shop_id', $shop->id);

        $queryBuilder->whereNull('products.exclusive_for_customer_id');


        if ($bucket == 'current') {
            $queryBuilder->whereIn('products.state', [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING]);
            foreach (IndexProductsInCatalogue::make()->getElementGroups($shop, $bucket) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        } elseif ($bucket == 'discontinued') {
            $queryBuilder->where('products.state', ProductStateEnum::DISCONTINUED);
        } elseif ($bucket == 'in_process') {
            $queryBuilder->where('products.state', ProductStateEnum::IN_PROCESS);
        } else {
            foreach (IndexProductsInCatalogue::make()->getElementGroups($shop) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }

        $interval = request()->input('interval', 'all');

        $customersInvoicedColumn = $interval === 'all'
            ? 'NULL'
            : "asset_ordering_intervals.customers_invoiced_{$interval}_ly";

        $salesLyColumn = $interval === 'all'
            ? 'NULL'
            : "asset_sales_intervals.sales_grp_currency_{$interval}_ly";

        $invoicesLyColumn = $interval === 'all'
            ? 'NULL'
            : "asset_ordering_intervals.invoices_{$interval}_ly";

        $queryBuilder
            ->defaultSort('products.code')
            ->select([
                'products.id',
                'products.code',
                'products.name',
                'products.state',
                'products.price',
                'products.created_at',
                'products.updated_at',
                'products.slug',
                DB::raw("asset_ordering_intervals.customers_invoiced_{$interval} as customers_invoiced"),
                DB::raw("{$customersInvoicedColumn} as customers_invoiced_ly"),
                DB::raw("asset_sales_intervals.sales_grp_currency_{$interval} as sales"),
                DB::raw("{$salesLyColumn} as sales_ly"),
                DB::raw("asset_ordering_intervals.invoices_{$interval} as invoices"),
                DB::raw("{$invoicesLyColumn} as invoices_ly"),
                DB::raw("'{$interval}' as current_interval"),
            ])
            ->selectRaw("'{$shop->currency->code}' as currency_code");

        return $queryBuilder->allowedSorts([
                'code',
                'name',
                'shop_slug',
                'department_slug',
                'family_slug',
                AllowedSort::custom(
                    'customers_invoiced',
                    new class ($interval) implements Sort {
                        public function __construct(private string $interval)
                        {
                        }

                        public function __invoke(Builder $query, bool $descending, string $property)
                        {
                            $direction = $descending ? 'desc' : 'asc';
                            $query->orderBy("asset_ordering_intervals.customers_invoiced_{$this->interval}", $direction);
                        }
                    }
                ),
                AllowedSort::custom(
                    'sales',
                    new class ($interval) implements Sort {
                        public function __construct(private string $interval)
                        {
                        }

                        public function __invoke(Builder $query, bool $descending, string $property)
                        {
                            $direction = $descending ? 'desc' : 'asc';
                            $query->orderBy("asset_sales_intervals.sales_grp_currency_{$this->interval}", $direction);
                        }
                    }
                ),
                AllowedSort::custom(
                    'invoices',
                    new class ($interval) implements Sort {
                        public function __construct(private string $interval)
                        {
                        }

                        public function __invoke(Builder $query, bool $descending, string $property)
                        {
                            $direction = $descending ? 'desc' : 'asc';
                            $query->orderBy("asset_ordering_intervals.invoices_{$this->interval}", $direction);
                        }
                    }
                ),
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop $shop, ?array $modelOperations = null, $prefix = null, string $bucket = null, $sales = true): Closure
    {
        return function (InertiaTable $table) use ($shop, $modelOperations, $prefix, $bucket, $sales) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($bucket == 'current' || $bucket == 'all') {
                foreach (IndexProductsInCatalogue::make()->getElementGroups($shop, $bucket) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }

            if ($sales) {
                $table->withInterval();
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('product'),__('products')])
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => match ($bucket) {
                            'in_process' => __("There is no products in process"),
                            'discontinued' => __('There is no discontinued products'),
                            default => __("No products found"),
                        },


                        'count' => match ($bucket) {
                            'current' => $shop->stats->number_current_products,
                            'in_process' => $shop->stats->number_products_state_in_process,
                            'discontinued' => $shop->stats->number_products_state_discontinued,
                            default => $shop->stats->number_products,
                        }

                    ]
                );


            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customers_invoiced', label: __('Customers'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'customers_invoiced_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, searchable: true, align: 'right')
                ->column(key: 'sales', label: __('Sales'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'sales_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, searchable: false, align: 'right')
                ->column(key: 'invoices', label: __('Invoices'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'invoices_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, searchable: false, align: 'right');
        };
    }

}
