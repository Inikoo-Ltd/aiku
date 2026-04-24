<?php

/*
 * author Louis Perez
 * created on 23-04-2026-09h-15m
 * github: https://github.com/louis-perez
 * copyright 2026
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
use App\Models\Masters\MasterAsset;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProductsWithIndependentTradeUnit extends OrgAction
{
    use WithDepartmentSubNavigation;
    use WithFamilySubNavigation;
    use WithCollectionSubNavigation;
    use WithCatalogueAuthorisation;

    private string $bucket;


    public function getElementGroups(Shop|MasterAsset $shop, $bucket = null): array
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
                'products.rrp',
                'products.created_at',
                'products.updated_at',
                'products.discontinued_at',
                'products.slug',
                'products.web_images',
                'available_quantity',
                'products.is_for_sale',
                'products.units',
                'products.unit',
                'products.created_at',
                'master_product_id',
            ])
            ->selectRaw("'{$shop->currency->code}'  as currency_code")
            ->leftJoin('product_stats', 'products.id', 'product_stats.product_id');

        $queryBuilder
            ->leftJoin('variants', 'variants.id', 'products.variant_id')
            ->leftJoin('product_categories', 'product_categories.id', 'products.family_id')
            ->where('products.not_follow_master_trade_units', true)
            ->with('orgStocks')
            ->addSelect([
                'variants.slug as variant_slug',
                'variants.code as variant_code',
                'products.is_variant_leader',
                'product_categories.slug as family_slug',
            ]);

        return $queryBuilder->allowedSorts([
            'code',
            'name',
            'shop_slug',
            'department_slug',
            'family_slug',
            'price',
            'rrp',
            'units',
            'available_quantity',
            'variant_slug',
            'created_at',
            'discontinued_at',
        ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop $shop, ?array $modelOperations = null, $prefix = null, ?string $bucket = null): Closure
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

            $table
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'product_org_stocks', label: __('SKU'), canBeHidden: false, sortable: true, searchable: false, type: 'icon');

            $table
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'price', label: __('Price/outer'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'rrp_per_unit', label: __('RRP/unit'), canBeHidden: false, sortable: true, searchable: true, align: 'right');

            if ($bucket !== 'discontinued') {
                $table->column(key: 'available_quantity', label: __('Stock'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
                $table->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            }

            if ($bucket == 'discontinued') {
                $table->column(key: 'discontinued_at', label: __('Discontinued At'), canBeHidden: false, sortable: true, searchable: true, type: 'date');
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
                    'name'       => 'grp.org.shops.show.catalogue.products.independent_products.current.index',
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
                    'name'       => 'grp.org.shops.show.catalogue.products.independent_products.in_process.index',
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
                    'name'       => 'grp.org.shops.show.catalogue.products.independent_products.discontinued.index',
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
                    'name'       => 'grp.org.shops.show.catalogue.products.independent_products.all.index',
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

        $navigation    = ProductsTabsEnum::navigationExcept([ProductsTabsEnum::INDEX_ORDERING]);
        $subNavigation = $this->getShopProductsSubNavigation($shop);

        $title = __('Independent Products');
        if ($this->bucket == 'discontinued') {
            $title = __('Discontinued Independent Products');
        } elseif ($this->bucket == 'in_process') {
            $title = __('Independent Products In Process');
        } elseif ($this->bucket == 'current') {
            $title = __('Current Independent Products');
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
                    'actions'       => [
                    ]
                ],
                'data'                         => ProductsResource::collection($products),
                'editable_table'               => true,
                'shop_id'                      => $shop->id,
                'tabs'                         => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],
                'variantSlugs'                 => ProductsResource::collection($products)->pluck('variant_slug')->filter()->unique()->mapWithKeys(fn ($slug) => [$slug => productCodeToHexCode($slug)]),
                'hide_sku_in_name_column'      => true,
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

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Independent Products'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.catalogue.products.independent_products.current.index', =>
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
            'grp.org.shops.show.catalogue.products.independent_products.in_process.index' =>
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
            'grp.org.shops.show.catalogue.products.independent_products.discontinued.index' =>
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
            'grp.org.shops.show.catalogue.products.independent_products.all.index' =>
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
