<?php

/*
 * Author Louis Perez
 * Created on 06-08-2026-11h-55m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\UI\Catalogue\ProductsTabsEnum;
use App\Http\Resources\Catalogue\ProductsWithMismatchFamilyResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProductsWithMismatchedFamily extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->parent instanceof MasterShop) {
            $this->canEdit = $request->user()->authTo("masters.edit");
            $this->canEditPrices = $request->user()->authTo("masters.price_edit");
            $this->canEditOffers = $request->user()->authTo("masters.offer_edit");

            return $request->user()->authTo("masters.view");
        } else {
            $this->canEdit = $request->user()->authTo("products.{$this->shop->id}.edit");
            $this->canDelete = $request->user()->authTo("products.{$this->shop->id}.edit");

            return $request->user()->authTo(
                [
                    "products.{$this->shop->id}.view",
                    "web.$this->shop->id.view",
                    "group-webmaster.view",
                    "accounting.{$this->shop->organisation_id}.view"
                ]
            );
        }
    }

    private MasterShop|Shop $parent;

    public function getElementGroups(MasterShop|Shop $parent): array
    {
        $labels = ProductStateEnum::labels();

        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    $labels,
                    collect($labels)->mapWithKeys(fn ($val, $key) => [$key => null])->toArray()
                ),
                'engine' => function ($query, $elements) {
                    $query->whereIn('products.state', $elements);
                }

            ],
        ];
    }

    public function handle(MasterShop|Shop $parent, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->where('products.is_main', true);

        $queryBuilder->whereNull('products.exclusive_for_customer_id');

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        $queryBuilder
            ->leftJoin('organisations', 'organisations.id', 'products.organisation_id')
            ->leftJoin('shops', 'shops.id', 'products.shop_id')
            ->leftJoin('product_categories as family', 'family.id', 'products.family_id')
            ->leftJoin('master_assets as ma', 'ma.id', 'products.master_product_id')
            ->leftJoin('master_product_categories as master_family', 'master_family.id', 'ma.master_family_id');



        if ($parent instanceof Shop) {
            $queryBuilder->where('products.shop_id', $parent->id);
        } else {
            $queryBuilder->where('ma.master_shop_id', $parent->id);
        }

        $queryBuilder
            ->whereNotNull('ma.id')
            ->whereColumn('family.master_product_category_id', '!=', 'master_family.id');

        $currency = $parent instanceof Shop ? $parent->currency : group()->currency;

        $queryBuilder
            ->defaultSort([
                'products.code',
                'products.shop_id'
            ])
            ->select([
                'products.id',
                'products.slug',
                'products.code',
                'products.name',
                'products.state',
                'products.price',
                'products.rrp',
                'products.created_at',
                'products.updated_at',
                'products.discontinued_at',
                'products.web_images',
                'available_quantity',
                'products.is_for_sale',
                'products.units',
                'products.unit',
                'master_product_id',
                'organisations.slug as organisation_slug',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'family.slug as family_slug',
                'family.code as family_code',
                'master_family.id as master_family_id',
                'master_family.slug as master_family_slug',
                'master_family.code as master_family_code',
            ])
            ->selectRaw("'{$currency->code}'  as currency_code")
            ->leftJoin('product_stats', 'products.id', 'product_stats.product_id');

        return $queryBuilder
            ->allowedSorts([
                'state',
                'code',
                'shop_code',
                'name',
                'family_code',
                'master_family_code',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(MasterShop|Shop $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
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
                        'title' => __("No products found"),
                        'count' => 0
                    ]
                );

            $table
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);

            if ($parent instanceof MasterShop) {
                $table
                    ->column(key: 'shop_code', label: __('Shop'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'family_code', label: __('Family Code'), canBeHidden: false, sortable: true, searchable: false)
                ->column(key: 'master_family_code', label: __('Expected Family Code (Master Product)'), canBeHidden: false, sortable: true, searchable: false);
        };
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsWithMismatchFamilyResource::collection($products);
    }

    public function htmlResponse(LengthAwarePaginator $products, ActionRequest $request): Response
    {
        $navigation    = ProductsTabsEnum::navigationExcept([ProductsTabsEnum::INDEX_ORDERING, ProductsTabsEnum::SALES]);


        $title = __('Products with Mismatched Family');

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
                'data'                         => ProductsWithMismatchFamilyResource::collection($products),
                'tabs'                         => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],
                ProductsTabsEnum::INDEX->value => $this->tab == ProductsTabsEnum::INDEX->value ?
                    fn () => ProductsWithMismatchFamilyResource::collection($products)
                    : Inertia::optional(fn () => ProductsWithMismatchFamilyResource::collection($products)),
            ]
        )
        ->table($this->tableStructure($this->parent, prefix: ProductsTabsEnum::INDEX->value));
    }


    public function inMasterShop(MasterShop $masterShop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterShop;
        $this->initialisationFromGroup(group(), $request)->withTab(ProductsTabsEnum::values());

        return $this->handle($masterShop, ProductsTabsEnum::INDEX->value);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(ProductsTabsEnum::values());

        return $this->handle($shop, ProductsTabsEnum::INDEX->value);
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
                    'suffix' => '('.__('With Mismatch Family').') '.$suffix
                ]
            ];
        };


        return match ($routeName) {
            'grp.masters.master_shops.show.products.mismatched_families' =>
                array_merge(
                    ShowMasterShop::make()->getBreadcrumbs($this->parent),
                    $headCrumb(
                        [
                            'name'       => $routeName,
                            'parameters' => Arr::only($routeParameters, ['masterShop']),
                        ],
                        $suffix
                    ),
                ),

            'grp.org.shops.show.catalogue.products.mismatched_families.index', =>
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
