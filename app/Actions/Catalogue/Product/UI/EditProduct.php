<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:09:31 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Goods\TradeUnit\UI\GetTradeUnitShowcase;
use App\Actions\Inventory\OrgStock\Json\GetOrgStocksInProduct;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Http\Resources\Inventory\OrgStocksInProductResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Inventory\OrgStocksResource;
use App\Models\Goods\TradeUnit;

class EditProduct extends OrgAction
{
    use WithCatalogueAuthorisation;

    private Organisation|Shop|Fulfilment|ProductCategory $parent;

    public function handle(Product $product): Product
    {
        return $product;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, Product $product, ActionRequest $request): Product
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

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

    /**
     * @throws \Exception
     */
    public function htmlResponse(Product $product, ActionRequest $request): Response
    {
        $warning = null;
        if ($product->is_single_trade_unit) {
            $warning = [
                    'type'  => 'warning',
                    'title' => __('Important'),
                    'text'  => __('This product is associated with trade unit, for weights, ingredients etc edit the trade unit. Changing name or description will affect all shops/websites using same language.'),
                    'icon'  => ['fas', 'fa-exclamation-triangle']
                ];
        }


        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Editing product').' '.$product->code,
                'warning'     => $warning,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $product,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($product, $request),
                    'next'     => $this->getNext($product, $request),
                ],
                'pageHead'    => [
                    'title'   => $product->code,
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-cube'],
                            'title' => __('Goods')
                        ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'blueprint' => $this->getBlueprint($product),
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.product.update',
                            'parameters' => [
                                'product' => $product->id
                            ]

                        ],
                    ]
                ]

            ]
        );
    }


    /**
     * @throws \Exception
     */
    public function getBlueprint(Product $product): array
    {
        //$value = OrgStocksInProductResource::collection(GetOrgStocksInProduct::run($product))->resolve();


        $family = $product->family;
        if ($family) {
            $stateData = [
                'label' => $family->state->labels()[$family->state->value],
                'icon'  => $family->state->stateIcon()[$family->state->value]['icon'],
                'class' => $family->state->stateIcon()[$family->state->value]['class']
            ];

            $familyOptions = [
                'id'                      => $family->id,
                'code'                    => $family->code,
                'state'                   => $stateData,
                'name'                    => $family->name,
                'number_current_products' => $family->stats->number_current_products,

            ];
        } else {
            $familyOptions = [
                'id'                      => null,
                'code'                    => null,
                'state'                   => null,
                'name'                    => null,
                'number_current_products' => null,

            ];
        }

        $barcodes = $product->tradeUnits->pluck('barcode')->filter()->unique();


        $nameFields = [
            'name'              => [
                'type'        => 'input',
                'label'       => __('Name'),
                'information' => __('This will displayed as H1 in the product page on website and in orders and invoices.'),
                'options'     => [
                    'counter' => true,
                ],
                'value'       => $product->name
            ],
            'description'       => [
                'type'        => 'textEditor',
                'label'       => __('Description'),
                'information' => __('This show in product webpage'),
                'options'     => [
                    'counter' => true,
                ],
                'value'       => $product->description
            ],
            'description_extra' => [
                'type'        => 'textEditor',
                'label'       => __('Extra description'),
                'information' => __('This above product specification in product webpage'),
                'options'     => [
                    'counter' => true,
                ],
                'value'       => $product->description_extra
            ],

        ];

        if ($product->webpage) {
            $webpage           = $product->webpage;
            $webpageNameFields = [
                'webpage_breadcrumb_label' => [
                    'type'        => 'input',
                    'label'       => __('Breadcrumb label').' ('.__('Optional').')',
                    'information' => __('To be used for the breadcrumbs, will use Meta Title if missing'),
                    'options'     => [
                        'counter' => true,
                    ],
                    'value'       => $webpage->breadcrumb_label,
                ],
                'webpage_title'            => [
                    'type'        => 'input',
                    'label'       => __('Meta Title').' (& '.__('Browser title').')',
                    'information' => __('This will be used for the title seen in the browser, and meta title for SEO'),
                    'options'     => [
                        'counter' => true,
                    ],
                    'value'       => $webpage->title,
                ],
                'webpage_description'      => [
                    'type'        => 'textarea',
                    'label'       => __('Meta Description'),
                    'information' => __('This will be used for the meta description'),
                    'options'     => [
                        'counter' => true,
                    ],
                    'value'       => $webpage->description,
                    "maxLength"   => 150,
                    "counter"     => true,
                ],
            ];

            $nameFields = array_merge($nameFields, $webpageNameFields);
        }

        return array_filter(
            [
                [
                    'label'  => __('Id'),
                    'icon'   => 'fa-light fa-fingerprint',
                    'fields' => [
                        'code'        => [
                            'type'  => 'input',
                            'label' => __('Code'),
                            'value' => $product->code
                        ],
                        'cpnp_number' => [
                            'hidden' => $product->is_single_trade_unit,
                            'type'   => 'input',
                            'label'  => __('CPNP Number'),
                            'value'  => $product->cpnp_number
                        ],
                        'scpn_number' => [
                            'hidden' => $product->is_single_trade_unit,
                            'type'   => 'input',
                            'label'  => __('SCPN number'),
                            'value'  => $product->scpn_number
                        ],
                        'ufi_number'  => [
                            'hidden' => $product->is_single_trade_unit,
                            'type'   => 'input',
                            'label'  => __('UFI Number'),
                            'value'  => $product->ufi_number
                        ],
                    ]
                ],
                [
                    'label'  => __('Name/Description'),
                    'icon'   => 'fa-light fa-tag',
                    'fields' => $nameFields
                ],

                [
                    'label'  => __('Pricing'),
                    'icon'   => 'fa-light fa-money-bill',
                    'fields' => [
                        'price'            => [
                            'type'     => 'input_number',
                            'label'    => __('Price'),
                            'required' => true,
                            'value'    => $product->price,
                            /*  'bind'  => [
                                 'suffix' => 'g'
                             ] */
                        ],
                        'rrp'              => [
                            'type'     => 'input_number',
                            'label'    => __('RRP'),
                            'required' => true,
                            'value'    => $product->rrp,
                            /* 'bind'  => [
                                'suffix' => 'g'
                            ] */
                        ],
                        'cost_price_ratio' => [
                            'type'        => 'input_number',
                            'bind'        => [
                                'maxFractionDigits' => 3
                            ],
                            'label'       => __('Pricing ratio'),
                            'placeholder' => __('Cost price ratio'),
                            'required'    => true,
                            'value'       => $product->cost_price_ratio,
                            'min'         => 0
                        ],
                    ]
                ],
                $product->is_single_trade_unit
                    ? []
                    :
                    [
                        'label'  => __('Properties'),
                        'title'  => __('id'),
                        'icon'   => 'fa-light fa-fingerprint',
                        'fields' => [
                            'unit'                 => [
                                'type'  => 'input',
                                'label' => __('Unit'),
                                'value' => $product->unit,
                            ],
                            'units'                => [
                                'type'  => 'input_number',
                                'label' => __('Units'),
                                'value' => $product->units,
                            ],
                            'marketing_weight'     => [
                                'type'        => 'input_number',
                                'label'       => __('Marketing weight'),
                                'information' => __('In product page, this will be displayed in specifications as Net Weight'),
                                'value'       => $product->marketing_weight,
                                'bind'        => [
                                    'suffix' => 'g'
                                ]
                            ],
                            'gross_weight'         => [
                                'type'        => 'input_number',
                                'label'       => __('Gross weight'),
                                'information' => __('In product page, this will be displayed in specifications as Shipping Weight'),
                                'value'       => $product->gross_weight,
                                'bind'        => [
                                    'suffix' => 'g'
                                ]
                            ],
                            'marketing_dimensions' => [
                                'type'        => 'input-dimension',
                                'information' => __('In product page, this will be displayed in specifications as Dimensions'),
                                'label'       => __('Marketing dimension'),
                                'value'       => $product->marketing_dimensions,
                            ],
                            'barcode'              => [
                                'type'     => 'select',
                                'label'    => __('Barcode'),
                                'value'    => $product->barcode,
                                'readonly' => $product->tradeUnits->count() == 1,
                                'options'  => $barcodes->mapWithKeys(function ($barcode) {
                                    return [$barcode => $barcode];
                                })->toArray()
                            ],


                        ]
                    ],
                //                [
                //                    'label'  => __('Parts'),
                //                    'icon' => 'fal fa-boxes',
                //                    'fields' => [
                //                        'org_stocks' => [
                //                            'type'         => 'product_parts',
                //                            'label'        => __('Parts'),
                //                            'full'         => true,
                //                            'fetch_route'  => [
                //                                'name'       => 'grp.json.org_stocks.index',
                //                                'parameters' => [
                //                                    'organisation' => $product->organisation_id,
                //                                ]
                //                            ],
                //                            'init_options' => OrgStocksResource::collection(GetOrgStocksInProduct::run($product))->resolve(),
                //                            'value'        => $value
                //                        ],
                //                    ]
                //                ],
                //                [
                //                    'label' => __('Trade unit'),
                //                    'icon' => 'fa-light fa-atom',
                //                    'fields' => [
                //                        'trade_units' => [
                //                            'label'      => __('Trade Units'),
                //                            'type' => 'edit-trade-unit-shop',
                //                            'value' => null,
                //                            'noSaveButton' => true,
                //                            'trade_units' => $product->tradeUnits ? $this->getDataTradeUnit($product->tradeUnits) : []
                //                        ]
                //                    ],
                //                ],
                [
                    'label'  => __('Family'),
                    'icon'   => 'fa-light fa-folder',
                    'fields' => [
                        'family_id' => [
                            'type'       => 'select_infinite',
                            'label'      => __('Family'),
                            'options'    => [
                                $familyOptions
                            ],
                            'fetchRoute' => [
                                'name'       => 'grp.json.shop.families',
                                'parameters' => [
                                    'shop' => $product->shop->id
                                ]
                            ],
                            'valueProp'  => 'id',
                            'labelProp'  => 'code',
                            'required'   => true,
                            'value'      => $product->family->id ?? null,
                            'type_label' => 'families'
                        ]
                    ],
                ],
            ]
        );
    }

    private function getDataTradeUnit($tradeUnits): array
    {
        return $tradeUnits->map(function (TradeUnit $tradeUnit) {
            return GetTradeUnitShowcase::run($tradeUnit);
        })->toArray();
    }

    public function getBreadcrumbs(Product $product, string $routeName, array $routeParameters): array
    {
        return ShowProduct::make()->getBreadcrumbs(
            parent: $this->parent,
            product: $product,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
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
            'shops.products.edit' => [
                'label' => $product->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'product' => $product->slug
                    ]

                ]
            ],
            'shops.show.products.edit' => [
                'label' => $product->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'shop'    => $product->shop->slug,
                        'product' => $product->slug
                    ]

                ]
            ],
            default => null,
        };
    }
}
