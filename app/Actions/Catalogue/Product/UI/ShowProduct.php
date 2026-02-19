<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 11:45:56 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\Product\GetProductImages;
use App\Actions\Catalogue\ProductCategory\UI\ShowDepartment;
use App\Actions\Catalogue\ProductCategory\UI\ShowFamily;
use App\Actions\Catalogue\ProductCategory\UI\ShowSubDepartment;
use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\Comms\BackInStockReminder\UI\ProductHasBackInStockReminders;
use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\CRM\Favourite\UI\IndexProductFavourites;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Goods\Asset\UI\IndexAssetTimeSeries;
use App\Actions\Goods\TradeUnit\UI\IndexTradeUnitsInProduct;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Inventory\OrgStock\UI\IndexOrgStocksInProduct;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\UI\Catalogue\ExternalShop\ProductInExternalTabsEnum;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Http\Resources\Catalogue\ProductHasBackInStockRemindersResource;
use App\Http\Resources\Catalogue\ProductFavouritesResource;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Http\Resources\CRM\CustomersResource;
use App\Http\Resources\Goods\AssetTimeSeriesResource;
use App\Http\Resources\Goods\TradeUnitsResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Inventory\OrgStocksResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowProduct extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithProductNavigation;

    private Group|Organisation|Shop|Fulfilment|ProductCategory $parent;


    public function handle(Product $product): Product
    {
        return $product;
    }

    public function inGroup(Product $product, ActionRequest $request): Product
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($product);
    }

    public function inOrganisation(Organisation $organisation, Product $product, ActionRequest $request): Product
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(ProductTabsEnum::values());

        return $this->handle($product);
    }

    public function asController(Organisation $organisation, Shop $shop, Product $product, ActionRequest $request): Product
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(ProductTabsEnum::values());

        return $this->handle($product);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, Product $product, ActionRequest $request): Product
    {
        $this->parent = $department;
        $this->initialisationFromShop($shop, $request)->withTab(ProductTabsEnum::values());

        return $this->handle($product);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartmentInShop(Organisation $organisation, Shop $shop, ProductCategory $subDepartment, Product $product, ActionRequest $request): Product
    {
        $this->parent = $subDepartment;
        $this->initialisationFromShop($shop, $request)->withTab(ProductTabsEnum::values());

        return $this->handle($product);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFamily(Organisation $organisation, Shop $shop, ProductCategory $family, Product $product, ActionRequest $request): Product
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request)->withTab(ProductTabsEnum::values());

        return $this->handle($product);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inFamilyInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $family, Product $product, ActionRequest $request): Product
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request)->withTab(ProductTabsEnum::values());

        return $this->handle($product);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFamilyInSubDepartmentInShop(Organisation $organisation, Shop $shop, ProductCategory $subDepartment, ProductCategory $family, Product $product, ActionRequest $request): Product
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request)->withTab(ProductTabsEnum::values());

        return $this->handle($product);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Product $product, ActionRequest $request): Product
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(ProductTabsEnum::values());

        return $this->handle($product);
    }

    public function htmlResponse(Product $product, ActionRequest $request): Response
    {
        $shop           = $product->shop;
        $isExternalShop = $shop->type == ShopTypeEnum::EXTERNAL;
        $hasMaster      = (bool)$product->masterProduct;

        $miniBreadcrumbs = [];
        if ($product->department) {
            $miniBreadcrumbs[] = [
                'label'   => $product->department->name,
                'to'      => [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show',
                    'parameters' => [
                        'organisation' => $product->organisation->slug,
                        'shop'         => $product->shop->slug,
                        'department'   => $product->department->slug,
                    ]
                ],
                'tooltip' => __('Department'),
                'icon'    => ['fal', 'folder-tree']
            ];
        }

        if ($product->subDepartment) {
            $miniBreadcrumbs[] = [
                'label'   => $product->subDepartment->name,
                'to'      => [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show',
                    'parameters' => [
                        'organisation'  => $product->organisation->slug,
                        'shop'          => $product->shop->slug,
                        'department'    => $product->department->slug,
                        'subDepartment' => $product->subDepartment->slug,
                    ]
                ],
                'tooltip' => __('Sub-department'),
                'icon'    => ['fal', 'folder-download']
            ];
        }

        if ($product->family) {
            $route = null;
            if ($product->subDepartment) {
                $route = [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show',
                    'parameters' => [
                        'organisation'  => $product->organisation->slug,
                        'shop'          => $product->shop->slug,
                        'department'    => $product->department->slug,
                        'subDepartment' => $product->subDepartment->slug,
                        'family'        => $product->family->slug,
                    ]
                ];
            } elseif ($product->department) {
                $route = [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show.families.show',
                    'parameters' => [
                        'organisation' => $product->organisation->slug,
                        'shop'         => $product->shop->slug,
                        'department'   => $product->department->slug,
                        'family'       => $product->family->slug,
                    ]
                ];
            }

            if ($route) {
                $miniBreadcrumbs[] = [
                    'label'      => $product->family->name,
                    'post_label' => $product->family->code,
                    'to'         => $route,
                    'tooltip'    => __('Family'),
                    'icon'       => ['fal', 'folder']
                ];
            }
        }

        $miniBreadcrumbs[] = [
            'label'   => $product->code,
            'to'      => null,
            'tooltip' => __('Product'),
            'icon'    => ['fal', 'cube']
        ];

        $actions = [];

        if ($this->canEdit) {
            $actions[] = [
                'type'  => 'button',
                'style' => 'edit',
                'label' => __('Edit'),
                'route' => [
                    'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                    'parameters' => $request->route()->originalParameters()
                ]
            ];
        }

        if ($product->webpage) {
            $actions = array_merge($actions, [
                [
                    'type'  => 'button',
                    'style' => 'edit',
                    'key'   => 'reindex',
                ],
                [
                    'type'  => 'button',
                    'style' => 'edit',
                    'label' => __('Webpage'),
                    'icon'  => ["fal", "fa-browser"],
                    'route' => [
                        'name'       => 'grp.org.shops.show.web.webpages.show',
                        'parameters' => [
                            'organisation' => $this->organisation->slug,
                            'shop'         => $this->shop->slug,
                            'website'      => $this->shop->website->slug,
                            'webpage'      => $product->webpage->slug
                        ]
                    ]
                ],
            ]);
        } elseif (!$product->is_minion_variant && !$isExternalShop) {
            $actions[] =
                [
                    'type'  => 'button',
                    'style' => 'edit',
                    'label' => __('Create Webpage'),
                    'icon'  => ["fal", "fa-browser"],
                    'route' => [
                        'name'       => 'grp.models.webpages.product.store',
                        'parameters' => $product->id,
                        'method'     => 'post'
                    ]
                ];
        }

        $productWeb = $product->webpage;

        if ($productWeb?->canonical_url) {
            $actions[] =
                [
                    'type'    => 'button',
                    'style'   => 'edit',
                    'icon'    => ["fal", "fa-external-link"],
                    'tooltip' => "Open website in a new tab",
                    'route'   => [
                        'url'       => $product->webpage?->canonical_url,
                        'openBlank' => true,
                    ]
                ];
        }

        $componentData = [
            ProductTabsEnum::SHOWCASE->value => $this->tab == ProductTabsEnum::SHOWCASE->value ?
                fn () => GetProductShowcase::run($product)
                : Inertia::lazy(fn () => GetProductShowcase::run($product)),

            'salesData' => $this->tab == ProductTabsEnum::SHOWCASE->value ?
                fn () => GetProductTimeSeriesData::run($product)
                : Inertia::lazy(fn () => GetProductTimeSeriesData::run($product)),

            ProductTabsEnum::SALES->value => $this->tab == ProductTabsEnum::SALES->value
                ?
                fn () => $product->asset
                    ? AssetTimeSeriesResource::collection(IndexAssetTimeSeries::run($product->asset, ProductTabsEnum::SALES->value))
                    : AssetTimeSeriesResource::collection(new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20))
                : Inertia::lazy(fn () => $product->asset
                    ? AssetTimeSeriesResource::collection(IndexAssetTimeSeries::run($product->asset, ProductTabsEnum::SALES->value))
                    : AssetTimeSeriesResource::collection(new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20))),

            ProductTabsEnum::TRADE_UNITS->value => $this->tab == ProductTabsEnum::TRADE_UNITS->value ?
                fn () => TradeUnitsResource::collection(IndexTradeUnitsInProduct::run($product))
                : Inertia::lazy(fn () => TradeUnitsResource::collection(IndexTradeUnitsInProduct::run($product))),

            ProductTabsEnum::STOCKS->value => $this->tab == ProductTabsEnum::STOCKS->value ?
                fn () => OrgStocksResource::collection(IndexOrgStocksInProduct::run($product))
                : Inertia::lazy(fn () => OrgStocksResource::collection(IndexOrgStocksInProduct::run($product))),

            ProductTabsEnum::HISTORY->value => $this->tab == ProductTabsEnum::HISTORY->value ?
                fn () => HistoryResource::collection(IndexHistory::run($product))
                : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($product))),

            ProductTabsEnum::CUSTOMERS->value => $this->tab == ProductTabsEnum::CUSTOMERS->value ?
                fn () => CustomersResource::collection(IndexCustomers::run($product))
                : Inertia::lazy(fn () => CustomersResource::collection(IndexCustomers::run($product))),
        ];

        if (!$isExternalShop) {
            $componentData = array_merge($componentData, [
                ProductTabsEnum::CONTENT->value => $this->tab == ProductTabsEnum::CONTENT->value ?
                    fn () => GetProductContent::run($product)
                    : Inertia::lazy(fn () => GetProductContent::run($product)),


                ProductTabsEnum::IMAGES->value => $this->tab == ProductTabsEnum::IMAGES->value ?
                    fn () => GetProductImages::run($product)
                    : Inertia::lazy(fn () => GetProductImages::run($product)),


                ProductTabsEnum::FAVOURITES->value => $this->tab == ProductTabsEnum::FAVOURITES->value ?
                    fn () => ProductFavouritesResource::collection(IndexProductFavourites::run($product))
                    : Inertia::lazy(fn () => ProductFavouritesResource::collection(IndexProductFavourites::run($product))),

                ProductTabsEnum::REMINDERS->value => $this->tab == ProductTabsEnum::REMINDERS->value ?
                    fn () => ProductHasBackInStockRemindersResource::collection(ProductHasBackInStockReminders::run($product))
                    : Inertia::lazy(fn () => ProductHasBackInStockRemindersResource::collection(ProductHasBackInStockReminders::run($product))),

                ProductTabsEnum::ATTACHMENTS->value => $this->tab == ProductTabsEnum::ATTACHMENTS->value ?
                    fn () => GetProductAttachment::run($product)
                    : Inertia::lazy(fn () => GetProductAttachment::run($product)),
            ]);
        }

        $productPage = Inertia::render(
            'Org/Catalogue/Product',
            [
                'title'                 => $product->code,
                'breadcrumbs'           => $this->getBreadcrumbs(
                    $this->parent,
                    $product,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'            => [
                    'previous' => $this->getPreviousModel($product, $request),
                    'next'     => $this->getNextModel($product, $request),
                ],
                'mini_breadcrumbs'      => $miniBreadcrumbs,
                'pageHead'              => [
                    'title'      => $product->code,
                    'model'      => __('Product'),
                    'icon'       =>
                        [
                            'icon'  => ['fal', 'fa-cube'],
                            'title' => __('Product')
                        ],
                    'afterTitle' => [
                        'label' => $product->name
                    ],
                    'iconRight'  => $product->state->stateIcon()[$product->state->value],
                    'actions'    => $actions

                ],
                'master'                => $hasMaster,
                'masterRoute'           => $hasMaster ? [
                    'name'       => 'grp.masters.master_shops.show.master_products.show',
                    'parameters' => [
                        'masterShop'    => $product->masterProduct->masterShop->slug,
                        'masterProduct' => $product->masterProduct->slug
                    ]
                ] : [],
                'tabs'                  => [
                    'current'    => $this->tab,
                    'navigation' => $isExternalShop ? ProductInExternalTabsEnum::navigation() : ProductTabsEnum::navigation()
                ],
                'is_external_shop'      => $isExternalShop,
                'family_slug'           => $product->family->slug,
                'product_state'         => $product->state->value,
                'webpage_canonical_url' => $product->webpage?->canonical_url,
                'is_single_trade_unit'  => $product->is_single_trade_unit,
                'trade_unit_slug'       => $product->tradeUnits?->first->slug,
                'luigi_data'            => $productWeb ? [
                    'webpage_id'            => $productWeb->id,
                    'last_reindexed'        => Arr::get($productWeb->website->settings, "luigisbox.last_reindex_at"),
                    'luigisbox_tracker_id'  => Arr::get($productWeb->website->settings, "luigisbox.tracker_id"),
                    'luigisbox_private_key' => Arr::get($productWeb->website->settings, "luigisbox.private_key"),
                    'luigisbox_lbx_code'    => Arr::get($productWeb->website->settings, "luigisbox.lbx_code"),
                ] : [],
                ...$componentData,
                'variant'       => $product->variant,
                'is_variant_leader' => $product->is_variant_leader,
            ]
        )
            ->table(IndexAssetTimeSeries::make()->tableStructure(prefix: ProductTabsEnum::SALES->value))
            ->table(IndexTradeUnitsInProduct::make()->tableStructure(prefix: ProductTabsEnum::TRADE_UNITS->value))
            ->table(IndexOrgStocksInProduct::make()->tableStructure(prefix: ProductTabsEnum::STOCKS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: ProductTabsEnum::HISTORY->value))
            ->table(IndexCustomers::make()->tableStructure(parent: $product, prefix: ProductTabsEnum::CUSTOMERS->value));

        if (!$isExternalShop) {
            $productPage = $productPage
                ->table(ProductHasBackInStockReminders::make()->tableStructure($product, ProductTabsEnum::REMINDERS->value))
                ->table(IndexProductFavourites::make()->tableStructure($product, ProductTabsEnum::FAVOURITES->value))
                ->table(IndexProductImages::make()->tableStructure($product, ProductTabsEnum::IMAGES->value));
        }

        return $productPage;
    }

    public function jsonResponse(Product $product): ProductsResource
    {
        return new ProductsResource($product);
    }

    public function getBreadcrumbs(Organisation|Shop|Fulfilment|ProductCategory $parent, Product $product, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (Product $product, array $routeParameters, $suffix, $suffixIndex = '') {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Products').$suffixIndex,
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $product->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.catalogue.products.current_products.show' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $product,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.products.current_products.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.products.current_products.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix,
                    ' ('.__('Current').')'
                )
            ),
            'grp.org.shops.show.catalogue.products.in_process_products.show' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $product,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.products.current_products.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.products.current_products.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix,
                    ' ('.__('In process').')'
                )
            ),
            'grp.org.shops.show.catalogue.products.orphan_products.show' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $product,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.products.orphan_products.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.products.orphan_products.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix,
                    ' ('.__('Orphan').')'
                )
            ),
            'grp.org.shops.show.catalogue.products.pending_back_in_stock_reminders.show' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $product,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.products.pending_back_in_stock_reminders.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.products.pending_back_in_stock_reminders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix,
                    ' ('.__('Pending Back-in-Stock').')'
                )
            ),
            'grp.org.shops.show.catalogue.products.out_of_stock_products.show' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $product,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.products.out_of_stock_products.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.products.out_of_stock_products.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix,
                    ' ('.__('Out Of Stock').')'
                )
            ),
            'grp.org.shops.show.catalogue.products.discontinued_products.show' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $product,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.products.discontinued_products.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.products.discontinued_products.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix,
                    ' ('.__('Discontinued').')'
                )
            ),
            'grp.org.shops.show.catalogue.products.all_products.show',
            'grp.org.shops.show.catalogue.products.all_products.webpage.create' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $product,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.products.all_products.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.products.all_products.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix,
                )
            ),

            'grp.org.fulfilments.show.products.show' =>
            array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $product,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.products.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.products.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.families.show.products.show' =>
            array_merge(
                ShowFamily::make()->getBreadcrumbs(
                    family: $parent,
                    routeName: 'grp.org.shops.show.catalogue.families.show',
                    routeParameters: Arr::only($routeParameters, ['organisation', 'shop', 'family'])
                ),
                $headCrumb(
                    $product,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.families.show.products.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'family'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.families.show.products.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.sub_departments.show.products.show', =>
            array_merge(
                ShowSubDepartment::make()->getBreadcrumbs(
                    subDepartment: $product->subDepartment,
                    routeName: $routeName,
                    routeParameters: $routeParameters
                ),
                $headCrumb(
                    $product,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.sub_departments.show.products.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'subDepartment'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.sub_departments.show.products.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.families.show.products.show' =>
            array_merge(
                ShowFamily::make()->getBreadcrumbs(
                    family: $parent,
                    routeName: 'grp.org.shops.show.catalogue.departments.show.families.show',
                    routeParameters: Arr::only($routeParameters, ['organisation', 'shop', 'department', 'family'])
                ),
                $headCrumb(
                    $product,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.families.show.products.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'department', 'family'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.families.show.products.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.sub_departments.show.families.show.products.show' =>
            array_merge(
                ShowFamily::make()->getBreadcrumbs(
                    family: $parent,
                    routeName: 'grp.org.shops.show.catalogue.sub_departments.show.families.show',
                    routeParameters: Arr::only($routeParameters, ['organisation', 'shop', 'subDepartment', 'family'])
                ),
                $headCrumb(
                    $product,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.sub_departments.show.families.show.products.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'subDepartment', 'family'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.sub_departments.show.families.show.products.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.products.show' =>
            array_merge(
                (new ShowDepartment())->getBreadcrumbs('grp.org.shops.show.catalogue.departments.show', $routeParameters),
                $headCrumb(
                    $product,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.products.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.products.show',
                            'parameters' => $routeParameters


                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

}
