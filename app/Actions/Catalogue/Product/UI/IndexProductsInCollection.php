<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 25 May 2025 19:56:14 Central Indonesia Time, Sanur, Plane KL-Shanghai
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\Collection\UI\ShowCollection;
use App\Actions\Catalogue\ProductCategory\UI\ShowDepartment;
use App\Actions\Catalogue\ProductCategory\UI\ShowFamily;
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
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProductsInCollection extends OrgAction
{
    use WithDepartmentSubNavigation;
    use WithFamilySubNavigation;
    use WithCollectionSubNavigation;
    use WithCatalogueAuthorisation;


    protected function getElementGroups(Collection $collection, $bucket = null): array
    {
        return [

            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    ProductStateEnum::labels($bucket),
                    ProductStateEnum::count($collection, $bucket)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('products.state', $elements);
                }

            ],
        ];
    }

    public function handle(Collection $collection, $prefix = null): LengthAwarePaginator
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

        $queryBuilder->join('model_has_collections', function ($join) use ($collection) {
            $join->on('products.id', '=', 'model_has_collections.model_id')
                ->where('model_has_collections.model_type', '=', 'Product')
                ->where('model_has_collections.collection_id', '=', $collection->id);
        });


        foreach ($this->getElementGroups($collection) as $key => $elementGroup) {
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
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'invoices_all',
                'sales_all',
                'customers_invoiced_all'
            ])
            ->leftJoin('product_stats', 'products.id', 'product_stats.product_id');

        return $queryBuilder->allowedSorts(['code', 'name', 'shop_slug', 'department_slug', 'family_slug'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Collection $collection, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($collection, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($collection) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations);

            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');


            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);


            $table->column(key: 'actions', label: __('action'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsResource::collection($products);
    }


    public function htmlResponse(LengthAwarePaginator $products, ActionRequest $request): Response
    {
        /** @var Collection $collection */
        $collection = $request->route('collection');

        $navigation = ProductsTabsEnum::navigation();

        $subNavigation = $this->getCollectionSubNavigation($collection);


        $title      = $collection->name;
        $model      = __('collection');
        $icon       = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => __('collection')
        ];
        $iconRight  = [
            'icon' => 'fal fa-cube',
        ];
        $afterTitle = [
            'label' => __('Products')
        ];


        $routes = [
            'dataList'     => [
                'name'       => 'grp.json.shop.catalogue.collection.products',
                'parameters' => [
                    'shop'  => $collection->shop->slug,
                    'scope' => $collection->slug
                ]
            ],
            'submitAttach' => [
                'name'       => 'grp.models.collection.attach-models',
                'parameters' => [
                    'collection' => $collection->id
                ]
            ],
            'detach'       => [
                'name'       => 'grp.models.collection.detach-models',
                'parameters' => [
                    'collection' => $collection->id
                ]
            ]
        ];


        $actions = [];
        if ($this->canEdit) {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'secondary',
                'key'     => 'attach-product',
                'icon'    => 'fal fa-plus',
                'tooltip' => __('Attach product to this collection'),
                'label'   => __('Attach product'),
            ];
        }


        return Inertia::render(
            'Org/Catalogue/Products',
            [
                'breadcrumbs'                  => $this->getBreadcrumbs(
                    $collection,
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
                    'actions'       => $actions,
                    'subNavigation' => $subNavigation,
                ],
                'routes'                       => $routes,
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
        )->table($this->tableStructure(collection: $collection, prefix: ProductsTabsEnum::INDEX->value))
            ->table($this->tableStructure(collection: $collection, prefix: ProductsTabsEnum::SALES->value));
    }


    public function asController(Organisation $organisation, Shop $shop, Collection $collection, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request)->withTab(ProductsTabsEnum::values());

        return $this->handle(collection: $collection);
    }


    public function getBreadcrumbs(Group|Shop|ProductCategory|Organisation|Collection $parent, string $routeName, array $routeParameters, string $suffix = null): array
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
                    $parent,
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
                    $parent,
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
                    $parent,
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

            'grp.org.shops.show.catalogue.collections.products.index' =>
            array_merge(
                ShowCollection::make()->getBreadcrumbs('grp.org.shops.show.catalogue.collections.show', $routeParameters),
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
