<?php

/*
 * Author Louis Perez
 * Created on 05-08-2026-11h-29m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
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
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProductsWithNoImage extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function getElementGroups(Shop $shop): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    array_merge(
                        ProductStateEnum::labels(),
                        ['not_for_sale' => __('Not for sale')]
                    ),
                    $this->getStateElementCounts($shop)
                ),

                'engine' => function ($query, $elements) {
                    $states = array_values(array_diff($elements, ['not_for_sale']));

                    $query->where(function ($query) use ($elements, $states) {
                        if ($states) {
                            $query->orWhereIn('products.state', $states);
                        }

                        if (in_array('not_for_sale', $elements)) {
                            $query->orWhere('products.is_for_sale', false);
                        }
                    });
                }

            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function getStateElementCounts(Shop $shop): array
    {
        $counts = $this->baseQuery($shop)
            ->selectRaw('count(*) filter (where products.state = ?) as in_process', [ProductStateEnum::IN_PROCESS->value])
            ->selectRaw('count(*) filter (where products.state = ?) as active', [ProductStateEnum::ACTIVE->value])
            ->selectRaw('count(*) filter (where products.state = ?) as discontinuing', [ProductStateEnum::DISCONTINUING->value])
            ->selectRaw('count(*) filter (where products.state = ?) as discontinued', [ProductStateEnum::DISCONTINUED->value])
            ->selectRaw('count(*) filter (where products.is_for_sale = false) as not_for_sale')
            ->first();

        return [
            'in_process'    => (int) $counts->in_process,
            'active'        => (int) $counts->active,
            'discontinuing' => (int) $counts->discontinuing,
            'discontinued'  => (int) $counts->discontinued,
            'not_for_sale'  => (int) $counts->not_for_sale,
        ];
    }

    protected function baseQuery(Shop $shop): Builder
    {
        return Product::query()
            ->where('products.shop_id', $shop->id)
            ->whereNull('products.exclusive_for_customer_id')
            ->whereNull('products.image_id');
    }

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

        $queryBuilder->where('products.shop_id', $shop->id);
        $queryBuilder->whereNull('products.exclusive_for_customer_id');
        $queryBuilder->whereNull('products.image_id');

        foreach ($this->getElementGroups($shop) as $key => $elementGroup) {
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
                'master_product_id',
            ]);

        return $queryBuilder->allowedSorts(['code', 'name', 'state',])
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

            foreach ($this->getElementGroups($shop) as $key => $elementGroup) {
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
                        'title' => __("No products found"),


                        'count' => $shop->stats->number_products, //$shop->stats->number_products_no_family

                    ]
                );


            $table
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon', sortable: true)
                ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function htmlResponse(LengthAwarePaginator $products, ActionRequest $request): Response
    {
        /** @var Shop $shop */
        $shop = $request->route('shop');

        $navigation    = ProductsTabsEnum::navigationExcept([ProductsTabsEnum::SALES, ProductsTabsEnum::INDEX_ORDERING]);

        $title = __('Products with No Image');

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
                    'is_negative'   => true,
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
                    : Inertia::optional(fn () => ProductsResource::collection($products)),

            ]
        )
        ->table($this->tableStructure(shop: $shop, prefix: ProductsTabsEnum::INDEX->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
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
            'grp.org.shops.show.catalogue.products.no_image_product.index', =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    trim('('.__('Missing Image').') '.$suffix)
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
