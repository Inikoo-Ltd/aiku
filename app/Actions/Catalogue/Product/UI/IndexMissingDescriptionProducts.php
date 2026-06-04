<?php

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Product\ProductStateEnum;
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

class IndexMissingDescriptionProducts extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function getElementGroups(Shop $shop): array
    {
        $rawCounts = Product::where('is_main', true)
            ->where('shop_id', $shop->id)
            ->whereNull('exclusive_for_customer_id')
            ->where(function ($q) {
                $q->whereNull('description')->orWhere('description', '');
            })
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

    public function handle(Shop $shop, ?string $prefix = null): LengthAwarePaginator
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
        $queryBuilder->where('products.is_main', true);
        $queryBuilder->where('products.shop_id', $shop->id);
        $queryBuilder->whereNull('products.exclusive_for_customer_id');
        $queryBuilder->where(function ($query) {
            $query->whereNull('products.description')->orWhere('products.description', '');
        });

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
                'products.slug',
                'products.web_images',
                'products.unit',
                'products.units',
                'products.is_for_sale',
                'products.master_product_id',
            ]);

        return $queryBuilder
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop $shop, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($shop, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
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
                ->withEmptyState(['title' => __('No products without description found')]);

            $table
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function htmlResponse(LengthAwarePaginator $products, ActionRequest $request): Response
    {
        $title = __('Products without description');

        return Inertia::render(
            'Org/Catalogue/MissingDescriptionProducts',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title'    => $title,
                    'model'    => '',
                    'icon'     => [
                        'icon'  => ['fal', 'fa-cube'],
                        'title' => $title,
                    ],
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-align-left'],
                        'title' => $title,
                    ],
                ],
                'data'        => ProductsResource::collection($products),
            ]
        )->table($this->tableStructure($this->shop));
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
                        'icon'  => 'fal fa-bars',
                    ],
                    'suffix' => trim('('.__('Without description').') '.$suffix),
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.catalogue.products.missing_description_products.index' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    ['name' => $routeName, 'parameters' => $routeParameters],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle(shop: $shop);
    }
}
