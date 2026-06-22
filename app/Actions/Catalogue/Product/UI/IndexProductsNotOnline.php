<?php

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\UI\Catalogue\ProductsTabsEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
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

class IndexProductsNotOnline extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function liveWebpageExistsSql(): string
    {
        return "exists (select 1 from webpages w where w.id = products.webpage_id and w.state = '".WebpageStateEnum::LIVE->value."')";
    }

    public function getElementGroups(Shop $shop): array
    {
        $rawCounts = Product::where('is_main', true)
            ->where('shop_id', $shop->id)
            ->whereNull('exclusive_for_customer_id')
            ->where('is_for_sale', true)
            ->whereRaw('not '.$this->liveWebpageExistsSql())
            ->whereIn('state', [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING, ProductStateEnum::IN_PROCESS])
            ->selectRaw('state, count(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state')
            ->toArray();

        $counts = array_fill_keys(array_keys(ProductStateEnum::labels()), 0);
        foreach ($rawCounts as $state => $count) {
            $counts[$state] = $count;
        }

        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(ProductStateEnum::labels(), $counts),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('products.state', $elements);
                },
            ],
        ];
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
        $queryBuilder->orderBy('products.state');

        $queryBuilder->leftJoin('asset_sales_intervals', 'products.asset_id', 'asset_sales_intervals.asset_id');
        $queryBuilder->leftJoin('asset_ordering_intervals', 'products.asset_id', 'asset_ordering_intervals.asset_id');
        $queryBuilder->leftJoin('webpages', 'products.webpage_id', 'webpages.id');

        $queryBuilder->where('products.is_main', true);
        $queryBuilder->where('products.shop_id', $shop->id);
        $queryBuilder->whereNull('products.exclusive_for_customer_id');
        $queryBuilder->where('products.is_for_sale', true);
        $queryBuilder->whereRaw('not '.$this->liveWebpageExistsSql());
        $queryBuilder->whereIn('products.state', [
            ProductStateEnum::ACTIVE,
            ProductStateEnum::DISCONTINUING,
            ProductStateEnum::IN_PROCESS
        ]);

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
                'products.available_quantity',
                'products.is_for_sale',
                'products.units',
                'products.unit',
                'products.master_product_id',
                'products.webpage_id',
                'webpages.state as webpage_state',
                'products.available_quantity'
            ])
            ->selectRaw($this->liveWebpageExistsSql().' as has_live_webpage');

        return $queryBuilder->allowedSorts([
            'code',
            'name',
            'state',
            'price',
            'webpage_state',
            'available_qty',
        ])
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
                        'title' => __('No products found'),
                        'count' => $shop->stats->number_products,
                    ]
                );

            $table
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'webpage_state', label: ['fal', 'fa-browser'], type: 'icon', canBeHidden: false, sortable: true, searchable: false, tooltip: 'Webpage State')
                ->column(key: 'price', label: __('Price/outer'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'available_quantity', label: __('Available Qty'), canBeHidden: false, sortable: true, searchable: false)
                ->defaultSort('code');
        };
    }

    public function htmlResponse(LengthAwarePaginator $products, ActionRequest $request): Response
    {
        /** @var Shop $shop */
        $shop = $request->route('shop');

        $navigation = ProductsTabsEnum::navigationExcept([ProductsTabsEnum::SALES, ProductsTabsEnum::INDEX_ORDERING]);

        $title = __('Products not Online');

        $icon = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => $title
        ];

        return Inertia::render(
            'Org/Catalogue/Products',
            [
                'breadcrumbs'                  => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'                        => $title,
                'pageHead'                     => [
                    'title'      => $title,
                    'model'      => null,
                    'icon'       => $icon,
                    'afterTitle' => null,
                    'iconRight'  => null,
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
            'grp.org.shops.show.catalogue.products.not_online_products.index' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    trim('('.__('Not Online').') '.$suffix)
                )
            ),
            default => []
        };
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request)->withTab(ProductsTabsEnum::values());

        return $this->handle(shop: $shop, prefix: ProductsTabsEnum::INDEX->value);
    }
}
