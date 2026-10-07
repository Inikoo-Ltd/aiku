<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\Traits\WithDuplicatedBarcodeProducts;
use App\Enums\UI\Catalogue\ProductsTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\InertiaTable\InertiaTable;
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

class IndexProductsWithDuplicatedBarcode extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithDuplicatedBarcodeProducts;

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value)
                    ->orWhereStartWith('products.barcode', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for($this->duplicatedBarcodeProducts($shop));

        $queryBuilder
            /** The listings sharing a barcode sit together, that is the decision to be made. */
            ->defaultSort([
                'products.barcode',
                'products.code',
            ])
            ->select([
                'products.id',
                'products.slug',
                'products.code',
                'products.name',
                'products.barcode',
                'products.state',
                'products.price',
                'products.rrp',
                'products.units',
                'products.unit',
                'products.is_for_sale',
                'products.web_images',
                'products.created_at',
                'products.updated_at',
                'products.discontinued_at',
                'products.master_product_id',
                'available_quantity',
            ])
            ->selectRaw("'{$shop->currency->code}'  as currency_code")
            ->leftJoin('product_stats', 'products.id', 'product_stats.product_id');

        return $queryBuilder
            ->allowedSorts([
                'code',
                'name',
                'barcode',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop $shop, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($shop, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __('No listing in this shop shares a barcode with another'),
                        'count' => $shop->stats->number_products_with_duplicated_barcode,
                    ]
                );

            $table
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                ->column(key: 'barcode', label: __('Barcode'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'units', label: __('Units'), canBeHidden: false, sortable: false, searchable: false, align: 'right');
        };
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsResource::collection($products);
    }

    public function htmlResponse(LengthAwarePaginator $products, ActionRequest $request): Response
    {
        $title = __('Listings sharing a barcode');

        return Inertia::render(
            'Org/Catalogue/Products',
            [
                'breadcrumbs'                  => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'                        => $title,
                'pageHead'                     => [
                    'title'       => $title,
                    'is_negative' => true,
                    'icon'        => [
                        'icon'  => ['fal', 'fa-barcode'],
                        'title' => $title
                    ],
                    'subtitle'    => __('A shop refuses a second listing carrying the same GTIN. Open each one and decide which listing keeps it.'),
                ],
                'data'                         => ProductsResource::collection($products),
                'tabs'                         => [
                    'current'    => $this->tab,
                    'navigation' => ProductsTabsEnum::navigationExcept([ProductsTabsEnum::INDEX_ORDERING, ProductsTabsEnum::SALES]),
                ],
                ProductsTabsEnum::INDEX->value => $this->tab == ProductsTabsEnum::INDEX->value ?
                    fn () => ProductsResource::collection($products)
                    : Inertia::optional(fn () => ProductsResource::collection($products)),
            ]
        )->table($this->tableStructure(shop: $request->route('shop'), prefix: ProductsTabsEnum::INDEX->value));
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request)->withTab(ProductsTabsEnum::values());

        return $this->handle($shop, ProductsTabsEnum::INDEX->value);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        return match ($routeName) {
            'grp.org.shops.show.catalogue.products.duplicated_barcodes.index' => array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => $routeName,
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Products'),
                            'icon'  => 'fal fa-bars'
                        ],
                        'suffix' => '('.__('Sharing a barcode').') '.$suffix
                    ]
                ]
            ),
            default => []
        };
    }
}
