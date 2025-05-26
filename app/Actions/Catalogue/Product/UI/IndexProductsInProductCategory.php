<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 11:47:26 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\ProductCategory\UI\ShowDepartment;
use App\Actions\Catalogue\ProductCategory\UI\ShowFamily;
use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\Catalogue\ProductsTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
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

class IndexProductsInProductCategory extends OrgAction
{
    use WithDepartmentSubNavigation;
    use WithFamilySubNavigation;
    use WithCatalogueAuthorisation;


    private ProductCategory $parent;

    private ?ProductCategory $grandParent = null;

    protected function getElementGroups(ProductCategory $productCategory): array
    {
        return [

            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    ProductStateEnum::labels(),
                    ProductStateEnum::count($productCategory)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('products.state', $elements);
                }

            ],
        ];
    }

    public function handle(ProductCategory $productCategory, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->leftJoin('organisations', 'products.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftJoin('asset_sales_intervals', 'products.asset_id', 'asset_sales_intervals.asset_id');
        $queryBuilder->leftJoin('asset_ordering_intervals', 'products.asset_id', 'asset_ordering_intervals.asset_id');
        $queryBuilder->where('products.is_main', true);

        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $queryBuilder->where('products.department_id', $productCategory->id);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            $queryBuilder->where('products.family_id', $productCategory->id);
        } else {
            abort(419);
        }

        foreach ($this->getElementGroups($productCategory) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }


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
            ])
            ->leftJoin('product_stats', 'products.id', 'product_stats.product_id');

        return $queryBuilder->allowedSorts(['code', 'name', 'shop_slug', 'department_slug', 'family_slug'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(ProductCategory $productCategory, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($productCategory, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($productCategory) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => $productCategory->type == ProductCategoryTypeEnum::DEPARTMENT ? __("There is no families in this department") : __("There is no products in this family"),
                        'count' => $productCategory->stats->number_products
                    ]
                );
            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsResource::collection($products);
    }


    public function htmlResponse(LengthAwarePaginator $products, ActionRequest $request): Response
    {
        $productCategory = $this->parent;

        $navigation = ProductsTabsEnum::navigation();

        $subNavigation = null;
        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $subNavigation = $this->getDepartmentSubNavigation($productCategory);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            $subNavigation = $this->getFamilySubNavigation($productCategory, $this->grandParent ?? $productCategory->shop, $request);
        }


        $title      = __('products');
        $icon       = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => __('product')
        ];
        $afterTitle = null;
        $iconRight  = null;
        $model      = null;

        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $title      = $productCategory->name;
            $model      = '';
            $icon       = [
                'icon'  => ['fal', 'fa-folder-tree'],
                'title' => __('Department')
            ];
            $iconRight  = [
                'icon' => 'fal fa-cube',
            ];
            $afterTitle = [
                'label' => __('Products')
            ];
        } elseif ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            $title      = $productCategory->name;
            $model      = '';
            $icon       = [
                'icon'  => ['fal', 'fa-folder'],
                'title' => __('Family')
            ];
            $iconRight  = [
                'icon' => 'fal fa-cube',
            ];
            $afterTitle = [
                'label' => __('Products')
            ];
        }


        return Inertia::render(
            'Org/Catalogue/Products',
            [
                'breadcrumbs'                  => $this->getBreadcrumbs(
                    $productCategory,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'                        => __('Products'),
                'pageHead'                     => [
                    'title'         => $title,
                    'model'         => $model,
                    'icon'          => $icon,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'actions'       => [
                        $this->canEdit
                        && $productCategory->type == ProductCategoryTypeEnum::FAMILY ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new product'),
                            'label'   => __('product'),
                            'route'   => [
                                'name'       => str_replace('index', 'create', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,


                    ],
                    'subNavigation' => $subNavigation,
                ],
                'data'                         => ProductsResource::collection($products),
                'tabs'                         => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],
                ProductsTabsEnum::INDEX->value => $this->tab == ProductsTabsEnum::INDEX->value ?
                    fn () => ProductsResource::collection($products)
                    : Inertia::lazy(fn () => ProductsResource::collection($products)),

                ProductsTabsEnum::SALES->value => $this->tab == ProductsTabsEnum::SALES->value ?
                    fn () => ProductsResource::collection($products)
                    : Inertia::lazy(fn () => ProductsResource::collection($products)),


            ]
        )->table($this->tableStructure(productCategory: $productCategory, prefix: ProductsTabsEnum::INDEX->value))
            ->table($this->tableStructure(productCategory: $productCategory, prefix: ProductsTabsEnum::SALES->value));
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inFamily(Organisation $organisation, Shop $shop, ProductCategory $family, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request)->withTab(ProductsTabsEnum::values());

        return $this->handle(productCategory: $family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFamilyInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $family, ActionRequest $request): LengthAwarePaginator
    {
        $this->grandParent = $department;
        $this->parent      = $family;
        $this->initialisationFromShop($shop, $request)->withTab(ProductsTabsEnum::values());

        return $this->handle(productCategory: $family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFamilyInSubDepartmentInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ProductCategory $family, ActionRequest $request): LengthAwarePaginator
    {
        $this->grandParent = $subDepartment;
        $this->parent      = $family;
        $this->initialisationFromShop($shop, $request)->withTab(ProductsTabsEnum::values());

        return $this->handle(productCategory: $family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $department;
        $this->initialisationFromShop($shop, $request)->withTab(ProductsTabsEnum::values());

        return $this->handle(productCategory: $department);
    }


    public function getBreadcrumbs(ProductCategory $productCategory, string $routeName, array $routeParameters, string $suffix = null): array
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
            'grp.org.shops.show.catalogue.products.current_products.index', =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    trim('('.__('Current').') '.$suffix)
                )
            ),
            'grp.org.shops.show.catalogue.products.in_process_products.index' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    trim('('.__('In process').') '.$suffix)
                )
            ),
            'grp.org.shops.show.catalogue.products.discontinued_products.index' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    trim('('.__('Discontinued').') '.$suffix)
                )
            ),
            'grp.org.shops.show.catalogue.products.all_products.index' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.products.index' =>
            array_merge(
                ShowDepartment::make()->getBreadcrumbs(
                    'grp.org.shops.show.catalogue.departments.show',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.families.show.products.index' =>
            array_merge(
                ShowFamily::make()->getBreadcrumbs(
                    $productCategory,
                    'grp.org.shops.show.catalogue.departments.show.families.show',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),

            'grp.org.shops.show.catalogue.families.show.products.index' =>
            array_merge(
                ShowFamily::make()->getBreadcrumbs(
                    $productCategory,
                    'grp.org.shops.show.catalogue.families.show',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show.products.index' =>
            array_merge(
                ShowFamily::make()->getBreadcrumbs(
                    $productCategory,
                    'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),


            default => []
        };
    }
}
