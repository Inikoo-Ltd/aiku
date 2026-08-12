<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Catalogue\ProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Products sold to named customers only. They are deliberately absent from the catalogue product
 * index and from the public site, and the departments that used to hold them have been retired, so
 * without this screen nobody in the office can see them at all.
 */
class IndexExclusiveProducts extends OrgAction
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

        $customerFilter = AllowedFilter::callback('customer', function ($query, $value) {
            $query->whereExists(function ($sub) use ($value) {
                $sub->from('product_has_exclusive_customers')
                    ->whereColumn('product_has_exclusive_customers.product_id', 'products.id')
                    ->where('product_has_exclusive_customers.customer_id', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->where('products.shop_id', $shop->id);
        $queryBuilder->where('products.is_main', true);
        $queryBuilder->whereExists(function ($sub) {
            $sub->from('product_has_exclusive_customers')
                ->whereColumn('product_has_exclusive_customers.product_id', 'products.id');
        });

        $queryBuilder
            ->defaultSort('products.code')
            ->select([
                'products.id',
                'products.code',
                'products.name',
                'products.state',
                'products.price',
                'products.slug',
                'products.created_at',
                'products.updated_at',
                'products.is_for_sale',
                'products.web_images',
                'products.units',
            ])
            ->selectRaw("(
                select string_agg(c.name, ', ' order by c.name)
                from product_has_exclusive_customers x
                join customers c on c.id = x.customer_id
                where x.product_id = products.id
            ) as exclusive_customers")
            ->selectRaw("'{$shop->currency->code}' as currency_code");

        return $queryBuilder
            ->allowedSorts(['code', 'name', 'state', 'price', 'units'])
            ->allowedFilters([$globalSearch, $customerFilter])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No products are sold exclusively to a customer'),
                ]);

            $table
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], sortable: true, type: 'icon')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'exclusive_customers', label: __('Sold only to'), canBeHidden: false)
                ->column(key: 'price', label: __('Price'), canBeHidden: false, sortable: true, align: 'right');
        };
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsResource::collection($products);
    }

    public function htmlResponse(LengthAwarePaginator $products, ActionRequest $request): Response
    {
        $title = __('Exclusive Products');

        $customers = Customer::where('shop_id', $this->shop->id)
            ->where('number_exclusive_products', '>', 0)
            ->orderByDesc('number_exclusive_products')
            ->get(['id', 'name', 'number_exclusive_products']);

        return Inertia::render(
            'Org/Catalogue/ExclusiveProducts',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title'      => $title,
                    'icon'       => ['icon' => ['fal', 'fa-gem'], 'title' => $title],
                    'afterTitle' => [
                        'label' => __('sold only to named customers').' · '.$customers->count().' '.__('customers'),
                    ],
                ],
                'data'        => ProductsResource::collection($products),
            ]
        )->table($this->tableStructure());
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowCatalogue::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.catalogue.exclusive_products.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Exclusive Products'),
                        'icon'  => 'fal fa-gem',
                    ],
                ],
            ]
        );
    }
}
