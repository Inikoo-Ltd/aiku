<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 11:47:26 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Masters\MasterAsset\UI\ShowMasterProduct;
use App\Actions\Masters\MasterAsset\WithMasterProductSubNavigation;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\UI\Catalogue\ProductsTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProductsInMasterProduct extends OrgAction
{
    use WithMasterProductSubNavigation;

    /**
     * @var \App\Models\Masters\MasterAsset
     */
    private MasterAsset $parent;

    public function handle(MasterAsset $masterAsset, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->leftJoin('shops', 'products.shop_id', 'shops.id');
        $queryBuilder->leftJoin('currencies', 'shops.currency_id', 'currencies.id');
        $queryBuilder->leftJoin('organisations', 'products.organisation_id', 'organisations.id');
        $queryBuilder->whereNull('products.exclusive_for_customer_id');
        $queryBuilder->where('products.master_product_id', $masterAsset->id);
        $queryBuilder->where('shops.state', '!=', ShopStateEnum::CLOSED->value);

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
                'products.rrp',
                'products.unit',
                'products.units',
                'products.asset_id',
                'products.master_product_id',
                'products.available_quantity',
                'products.is_for_sale',
                'shops.name as shop_name',
                'shops.code as shop_code',
                'currencies.code as currency_code',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->leftJoin('product_stats', 'products.id', 'product_stats.product_id')
            ->with('orgStocks');

        foreach (IndexProductsInCatalogue::make()->getElementGroups($masterAsset) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder->allowedSorts(['code', 'name', 'shop_code', 'units'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null, ?MasterAsset $masterAsset = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $masterAsset) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach (IndexProductsInCatalogue::make()->getElementGroups($masterAsset, 'all') as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __("There is no products"),
                    ]
                );
            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'shop_code', label: __('Shop'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'price', label: __('Price/outer'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'rrp_per_unit', label: __('RRP/unit'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'available_quantity', label: __('Stock'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'actions', label: '', canBeHidden: false);
        };
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsResource::collection($products);
    }

    public function htmlResponse(LengthAwarePaginator $products): \Illuminate\Http\Response|\Inertia\Response
    {
        $subNavigation   = $this->getMasterProductsSubNavigation($this->parent);
        $title           = $this->parent->name;
        $model           = '';
        $icon            = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => $this->parent->name
        ];
        $afterTitle      = [
            'label' => __('Products in Shop')
        ];
        $iconRight       = [
            'icon' => 'fal fa-store',
        ];

        return Inertia::render(
            'Org/Catalogue/Products',
            [
                    'breadcrumbs'                  => $this->getBreadcrumbs(
                        request()->route()->getName(),
                        request()->route()->originalParameters()
                    ),
                    'title'                        => $title,
                    'pageHead'                     => [
                        'title'         => $title,
                        'model'         => $model,
                        'icon'          => $icon,
                        'afterTitle'    => $afterTitle,
                        'iconRight'     => $iconRight,
                        'subNavigation' => $subNavigation,
                    ],
                    'data'                         => ProductsResource::collection($products),
                    'editable_table'               => false,
                    'tabs'                         => [
                        'current'    => $this->tab,
                        'navigation' => ProductsTabsEnum::navigationExcept([ProductsTabsEnum::SALES]),
                    ],
                    ProductsTabsEnum::INDEX->value => $this->tab == ProductsTabsEnum::INDEX->value ?
                        fn () => ProductsResource::collection($products)
                        : Inertia::lazy(fn () => ProductsResource::collection($products)),
                ]
        )->table($this->tableStructure(ProductsTabsEnum::INDEX->value, $this->parent));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null)
    {
        $headCrumb = function (array $routeParameters = [], ?string $suffix = null) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Products in Shop'),
                        'icon'  => 'fal fa-bars',

                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.masters.master_shops.show.master_products.mismatch_detected.products',
            'grp.masters.master_shops.show.master_departments.show.master_families.show.master_products.products',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.master_products.products',
            'grp.masters.master_shops.show.master_departments.show.master_products.products',
            'grp.masters.master_shops.show.master_families.master_products.products',
            'grp.masters.master_shops.show.master_products.products' =>
            array_merge(
                ShowMasterProduct::make()->getBreadcrumbs($this->parent, 'grp.masters.master_shops.show.master_products.show', $routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
        };
    }

    public function inMasterFamilyInMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamilies, MasterAsset $masterProduct, ActionRequest $request)
    {
        $this->initialisationFromGroup($masterProduct->group, $request)->withTab(ProductsTabsEnum::valuesExcept([ProductsTabsEnum::SALES]));
        $this->parent = $masterProduct;

        return $this->handle($masterProduct, ProductsTabsEnum::INDEX->value);
    }

    public function inMasterFamilyInMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamilies, MasterAsset $masterProduct, ActionRequest $request)
    {
        $this->initialisationFromGroup($masterProduct->group, $request)->withTab(ProductsTabsEnum::valuesExcept([ProductsTabsEnum::SALES]));
        $this->parent = $masterProduct;

        return $this->handle($masterProduct, ProductsTabsEnum::INDEX->value);
    }

    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterAsset $masterProduct, ActionRequest $request)
    {
        $this->initialisationFromGroup($masterProduct->group, $request)->withTab(ProductsTabsEnum::valuesExcept([ProductsTabsEnum::SALES]));
        $this->parent = $masterProduct;

        return $this->handle($masterProduct, ProductsTabsEnum::INDEX->value);
    }

    public function inMasterFamilyInMasterShop(MasterShop $masterShop, MasterProductCategory $masterFamily, MasterAsset $masterProduct, ActionRequest $request)
    {
        $this->initialisationFromGroup($masterProduct->group, $request)->withTab(ProductsTabsEnum::valuesExcept([ProductsTabsEnum::SALES]));
        $this->parent = $masterProduct;

        return $this->handle($masterProduct, ProductsTabsEnum::INDEX->value);
    }

    public function inMaster(MasterShop $masterShop, MasterAsset $masterProduct, ActionRequest $request)
    {
        $this->initialisationFromGroup($masterProduct->group, $request)->withTab(ProductsTabsEnum::valuesExcept([ProductsTabsEnum::SALES]));
        $this->parent = $masterProduct;

        return $this->handle($masterProduct, ProductsTabsEnum::INDEX->value);
    }

    public function inMasterProductMismatch(MasterShop $masterShop, MasterAsset $masterProduct, ActionRequest $request)
    {
        $this->initialisationFromGroup($masterProduct->group, $request)->withTab(ProductsTabsEnum::valuesExcept([ProductsTabsEnum::SALES]));
        $this->parent = $masterProduct;

        return $this->handle($masterProduct, ProductsTabsEnum::INDEX->value);
    }

}
