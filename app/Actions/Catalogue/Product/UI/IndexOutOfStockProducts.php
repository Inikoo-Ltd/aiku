<?php

/*
 * author Arya Permana - Kirin
 * created on 19-06-2025-08h-08m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\UI\Catalogue\ProductsTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOutOfStockProducts extends OrgAction
{
    use WithCatalogueAuthorisation;


    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->where('products.status', ProductStatusEnum::OUT_OF_STOCK);

        $queryBuilder->whereNull('products.exclusive_for_customer_id');

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
                'invoices_all',
                'sales_all',
                'customers_invoiced_all',
            ]);

        return $queryBuilder->allowedSorts(['code', 'name', 'state', 'price'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop $shop, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($shop, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => __("No products found"),


                        'count' => $shop->stats->number_products, //$shop->stats->number_products_no_family

                    ]
                );


            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function htmlResponse(LengthAwarePaginator $products, ActionRequest $request): Response
    {
        /** @var Shop $shop */
        $shop = $request->route('shop');

        $navigation    = ProductsTabsEnum::navigation();

        unset($navigation[ProductsTabsEnum::SALES->value]);

        $title = __('Out Of Stock Products');

        $icon       = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => $title
        ];
        $afterTitle = null;
        $iconRight  = null;
        $model      = null;


        return Inertia::render(
            'Org/Catalogue/Products',
            [
                'breadcrumbs'                  => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'                        => $title,
                'pageHead'                     => [
                    'title'         => $title,
                    'model'         => $model,
                    'icon'          => $icon,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                ],
                'data'                         => ProductsResource::collection($products),
                'tabs'                         => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],

                ProductsTabsEnum::INDEX->value => $this->tab == ProductsTabsEnum::INDEX->value ?
                    fn () => ProductsResource::collection($products)
                    : Inertia::lazy(fn () => ProductsResource::collection($products)),

            ]
        )->table($this->tableStructure(shop: $shop, prefix: ProductsTabsEnum::INDEX->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Products'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };


        return match ($routeName) {
            'grp.org.shops.show.catalogue.products.out_of_stock_products.index', =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    trim('('.__('Out Of Stock').') '.$suffix)
                )
            ),
            default => []
        };
    }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request)->withTab(ProductsTabsEnum::values());
        return $this->handle(shop:$shop, prefix: ProductsTabsEnum::INDEX->value);
    }

}
