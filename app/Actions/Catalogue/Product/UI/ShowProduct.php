<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 11:45:56 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\ProductCategory\UI\ShowDepartment;
use App\Actions\Catalogue\ProductCategory\UI\ShowFamily;
use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\CRM\BackInStockReminder\UI\IndexProductBackInStockReminders;
use App\Actions\CRM\Favourite\UI\IndexProductFavourites;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Goods\TradeUnit\UI\IndexTradeUnitsInProduct;
use App\Actions\Inventory\OrgStock\UI\IndexOrgStocksInProduct;
use App\Actions\Ordering\Order\UI\IndexOrders;
use App\Actions\OrgAction;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Http\Resources\Catalogue\ProductBackInStockRemindersResource;
use App\Http\Resources\Catalogue\ProductFavouritesResource;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Http\Resources\Goods\TradeUnitsResource;
use App\Http\Resources\Inventory\OrgStocksResource;
use App\Http\Resources\Sales\OrderResource;
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
    private Group|Organisation|Shop|Fulfilment|ProductCategory $parent;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->parent instanceof Organisation) {
            $this->canEdit = $request->user()->authTo(
                [
                    'org-supervisor.'.$this->organisation->id,
                ]
            );

            return $request->user()->authTo(
                [
                    'org-supervisor.'.$this->organisation->id,
                    'shops-view'.$this->organisation->id,
                ]
            );
        } elseif ($this->parent instanceof Group) {
            return $request->user()->authTo("group-overview");
        } else {
            $this->canEdit = $request->user()->authTo("products.{$this->shop->id}.edit");

            return $request->user()->authTo("products.{$this->shop->id}.view");
        }
    }

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
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Product $product, ActionRequest $request): Product
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(ProductTabsEnum::values());

        return $this->handle($product);
    }

    public function getProductTaxonomy(Product $product, ActionRequest $request): array
    {
        $routeName = $request->route()->getName();

        $family = null;
        if ($product->family) {
            $family = [
                'label'   => $product->family->code,
                'tooltip' => $product->family->code,
                 'name'   => $product->family->name,
                'route'   => match ($routeName) {
                    'grp.org.shops.show.catalogue.departments.show.families.show.products.show' => [
                        'name'       => 'grp.org.shops.show.catalogue.departments.show.families.show',
                        'parameters' => [
                            'organisation' => $this->parent->slug,
                            'shop'         => $product->shop->slug,
                            'department'   => $product->family->department->slug,
                            'family'       => $product->family->slug
                        ]
                    ],

                    default => [
                        'name'       => 'grp.org.shops.show.catalogue.families.show',
                        'parameters' => [
                            'organisation' => $this->parent->slug,
                            'shop'         => $product->shop->slug,
                            'family'       => $product->family->slug
                        ]
                    ]
                }

            ];
        }

        $department = null;
        if ($product->department) {
            $department = [
                'label'   => $product->department->code,
                'name'   => $product->department->name,
                'tooltip' => $product->department->name,
                'route'   => [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show',
                    'parameters' => [
                        'organisation' => $this->parent->slug,
                        'shop'         => $product->shop->slug,
                        'department'   => $product->department->slug
                    ]
                ]

            ];
        }


        return [
            'family'     => $family,
            'department' => $department
        ];
    }

    public function htmlResponse(Product $product, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Catalogue/Product',
            [
                'title'       => __('product'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->parent,
                    $product,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($product, $request),
                    'next'     => $this->getNext($product, $request),
                ],
                'pageHead'    => [
                    'title'      => $product->code,
                    'model'      => $this->parent?->code,
                    'icon'       =>
                        [
                            'icon'  => ['fal', 'fa-cube'],
                            'title' => __('product')
                        ],
                    'afterTitle' => [
                        'label' => $product->name
                    ],
                    'actions'    => [
                        $product->webpage
                            ?
                            [
                                'type'    => 'button',
                                'style'   => 'edit',
                                'tooltip' => __('To Webpage'),
                                'label'   => __('To Webpage'),
                                'icon'    => ["fal", "fa-drafting-compass"],
                                'route'   => [
                                    'name'       => 'grp.org.shops.show.web.webpages.show',
                                    'parameters' => [
                                        'organisation' => $this->organisation->slug,
                                        'shop'         => $this->shop->slug,
                                        'website'      => $this->shop->website->slug,
                                        'webpage'      => $product->webpage->slug
                                    ]
                                ]
                            ]
                            : [
                            'type'    => 'button',
                            'style'   => 'edit',
                            'tooltip' => __('Create Webpage'),
                            'label'   => __('Create Webpage'),
                            'icon'    => ["fal", "fa-drafting-compass"],
                            'route'   => [
                                'name'       => 'grp.models.webpages.product.store',
                                'parameters' => $product->id,
                                'method'     => 'post'
                            ]

                        ],
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,

                    ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => ProductTabsEnum::navigation()
                ],
                'taxonomy'    => $this->getProductTaxonomy($product, $request),


                ProductTabsEnum::SHOWCASE->value => $this->tab == ProductTabsEnum::SHOWCASE->value ?
                    fn() => GetProductShowcase::run($product)
                    : Inertia::lazy(fn() => GetProductShowcase::run($product)),

                ProductTabsEnum::ORDERS->value => $this->tab == ProductTabsEnum::ORDERS->value ?
                    fn() => OrderResource::collection(IndexOrders::run($product->asset))
                    : Inertia::lazy(fn() => OrderResource::collection(IndexOrders::run($product->asset))),

                ProductTabsEnum::FAVOURITES->value => $this->tab == ProductTabsEnum::FAVOURITES->value ?
                    fn() => ProductFavouritesResource::collection(IndexProductFavourites::run($product))
                    : Inertia::lazy(fn() => ProductFavouritesResource::collection(IndexProductFavourites::run($product))),

                ProductTabsEnum::REMINDERS->value => $this->tab == ProductTabsEnum::REMINDERS->value ?
                    fn() => ProductBackInStockRemindersResource::collection(IndexProductBackInStockReminders::run($product))
                    : Inertia::lazy(fn() => ProductBackInStockRemindersResource::collection(IndexProductBackInStockReminders::run($product))),

                ProductTabsEnum::TRADE_UNITS->value => $this->tab == ProductTabsEnum::TRADE_UNITS->value ?
                    fn() => TradeUnitsResource::collection(IndexTradeUnitsInProduct::run($product))
                    : Inertia::lazy(fn() => TradeUnitsResource::collection(IndexTradeUnitsInProduct::run($product))),

                ProductTabsEnum::STOCKS->value => $this->tab == ProductTabsEnum::STOCKS->value ?
                    fn() => OrgStocksResource::collection(IndexOrgStocksInProduct::run($product))
                    : Inertia::lazy(fn() => OrgStocksResource::collection(IndexOrgStocksInProduct::run($product))),


            ]
        )->table(IndexOrders::make()->tableStructure($product->asset, ProductTabsEnum::ORDERS->value))
            ->table(IndexProductBackInStockReminders::make()->tableStructure($product, ProductTabsEnum::REMINDERS->value))
            ->table(IndexTradeUnitsInProduct::make()->tableStructure(prefix: ProductTabsEnum::TRADE_UNITS->value))
            ->table(IndexOrgStocksInProduct::make()->tableStructure(prefix: ProductTabsEnum::STOCKS->value))
            ->table(IndexProductFavourites::make()->tableStructure($product, ProductTabsEnum::FAVOURITES->value));
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

    public function getPrevious(Product $product, ActionRequest $request): ?array
    {
        $previous = Product::where('slug', '<', $product->slug)->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Product $product, ActionRequest $request): ?array
    {
        $next = Product::where('slug', '>', $product->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Product $product, string $routeName): ?array
    {
        if (!$product) {
            return null;
        }

        return match ($routeName) {
            'shops.products.show' => [
                'label' => $product->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'product' => $product->slug,
                    ],
                ],
            ],
            'grp.org.shops.show.catalogue.products.show' => [
                'label' => $product->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->parent->slug,
                        'shop'         => $product->shop->slug,
                        'product'      => $product->slug,
                    ],
                ],
            ],
            'grp.org.fulfilments.show.products.show' => [
                'label' => $product->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->parent->slug,
                        'fulfilment'   => $product->shop->fulfilment->slug,
                        'product'      => $product->slug,
                    ],
                ],
            ],
            default => null,
        };
    }
}
