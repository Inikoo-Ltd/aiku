<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 25 May 2025 18:35:50 Central Indonesia Time, Plane KL-Shanghai
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\UI\Catalogue\ProductsTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\InertiaTable\InertiaTable;
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

class IndexProductsInCatalogue extends OrgAction
{
    use WithDepartmentSubNavigation;
    use WithFamilySubNavigation;
    use WithCollectionSubNavigation;
    use WithCatalogueAuthorisation;

    private string $bucket;


    public function getElementGroups(Shop $shop, $bucket = null): array
    {
        return [

            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    ProductStateEnum::labels($bucket),
                    ProductStateEnum::count($shop, $bucket)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('products.state', $elements);
                }

            ],
        ];
    }

    public function handle(Shop $shop, $prefix = null, $bucket = null): LengthAwarePaginator
    {
        if ($bucket) {
            $this->bucket = $bucket;
        }


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
        $queryBuilder->where('products.is_main', true);
        $queryBuilder->where('products.shop_id', $shop->id);
        $queryBuilder->whereNull('products.exclusive_for_customer_id');

        if ($bucket == 'current') {
            $queryBuilder->whereIn('products.state', [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING]);
            foreach ($this->getElementGroups($shop, $bucket) as $key => $elementGroup) {
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
            foreach ($this->getElementGroups($shop) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
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
                'available_quantity',
                'units',
            ])
            ->selectRaw("'{$shop->currency->code}'  as currency_code")
            ->leftJoin('product_stats', 'products.id', 'product_stats.product_id');

        return $queryBuilder->allowedSorts([
            'code',
            'name',
            'shop_slug',
            'department_slug',
            'family_slug',
            'price',
            'units',
            'available_quantity'
        ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop $shop, ?array $modelOperations = null, $prefix = null, string $bucket = null): Closure
    {
        return function (InertiaTable $table) use ($shop, $modelOperations, $prefix, $bucket) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($bucket == 'current' || $bucket == 'all') {
                foreach ($this->getElementGroups($shop, $bucket) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }

            $table
                ->withGlobalSearch()
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
            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'units', label: __('units'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'price', label: __('price'), canBeHidden: false, sortable: true, searchable: true);
            if ($bucket != 'discontinued') {
                $table->column(key: 'available_quantity', label: __('stock'), canBeHidden: false, sortable: true, searchable: true);
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsResource::collection($products);
    }

    public function getShopProductsSubNavigation(Shop $shop): array
    {
        $stats = $shop->stats;

        return [

            [
                'label'  => __('Current'),
                'root'   => 'grp.org.shops.show.catalogue.products.current_products.',
                'route'  => [
                    'name'       => 'grp.org.shops.show.catalogue.products.current_products.index',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->shop->slug
                    ]
                ],
                'number' => $stats->number_current_products
            ],

            [
                'label'  => __('In Process'),
                'root'   => 'grp.org.shops.show.catalogue.products.in_process_products.',
                'route'  => [
                    'name'       => 'grp.org.shops.show.catalogue.products.in_process_products.index',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->shop->slug
                    ]
                ],
                'number' => $stats->number_products_state_in_process
            ],
            [
                'label'  => __('Discontinued'),
                'root'   => 'grp.org.shops.show.catalogue.products.discontinued_products.',
                'route'  => [
                    'name'       => 'grp.org.shops.show.catalogue.products.discontinued_products.index',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->shop->slug
                    ]
                ],
                'number' => $stats->number_products_state_discontinued,
                'align'  => 'right'
            ],
            [
                'label'  => __('All'),
                'icon'   => 'fal fa-bars',
                'root'   => 'grp.org.shops.show.catalogue.products.all_products.',
                'route'  => [
                    'name'       => 'grp.org.shops.show.catalogue.products.all_products.index',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->shop->slug
                    ]
                ],
                'number' => $stats->number_products,
                'align'  => 'right'
            ],

        ];
    }

    public function htmlResponse(LengthAwarePaginator $products, ActionRequest $request): Response
    {
        /** @var Shop $shop */
        $shop = $request->route('shop');

        $navigation    = ProductsTabsEnum::navigation();
        $subNavigation = $this->getShopProductsSubNavigation($shop);


        $title = __('Products');
        if ($this->bucket == 'discontinued') {
            $title = __('Discontinued products');
        } elseif ($this->bucket == 'in_process') {
            $title = __('Products in process');
        } elseif ($this->bucket == 'current') {
            $title = __('Current products');
        }


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
                    fn () => ProductsResource::collection(IndexProducts::run($shop, ProductsTabsEnum::SALES->value, $this->bucket))
                    : Inertia::lazy(fn () => ProductsResource::collection(IndexProducts::run($shop, ProductsTabsEnum::SALES->value, $this->bucket))),


            ]
        )->table($this->tableStructure(shop: $shop, prefix: ProductsTabsEnum::INDEX->value, bucket: $this->bucket))
            ->table(IndexProducts::make()->tableStructure(shop: $shop, prefix: ProductsTabsEnum::SALES->value));
    }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->initialisationFromShop($shop, $request)->withTab(ProductsTabsEnum::values());

        return $this->handle(shop: $shop, prefix: ProductsTabsEnum::INDEX->value, bucket: $this->bucket);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function current(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'current';
        $this->initialisationFromShop($shop, $request)->withTab(ProductsTabsEnum::values());

        return $this->handle(shop: $shop, prefix: ProductsTabsEnum::INDEX->value, bucket: $this->bucket);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inProcess(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'in_process';
        $this->initialisationFromShop($shop, $request)->withTab(ProductsTabsEnum::values());

        return $this->handle(shop: $shop, prefix: ProductsTabsEnum::INDEX->value, bucket: $this->bucket);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function discontinued(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'discontinued';
        $this->initialisationFromShop($shop, $request)->withTab(ProductsTabsEnum::values());

        return $this->handle(shop: $shop, prefix: ProductsTabsEnum::INDEX->value, bucket: $this->bucket);
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


            default => []
        };
    }
}
