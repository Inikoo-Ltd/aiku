<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:09:31 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Helpers\LanguageResource;
use Illuminate\Support\Facades\DB;

class EditProduct extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithProductNavigation;

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
        $warning        = null;
        $warningText    = [];
        $hasMaster      = (bool)$product->masterProduct;
        $isExternalShop = $product->shop->type == ShopTypeEnum::EXTERNAL;

        if ($product->is_single_trade_unit) {
            $warningText[] = __('This product is associated with trade unit, for weights, ingredients etc edit the trade unit. Changing name or description will affect all shops/websites using same language.');
        }

        $forceFollowMasterProduct = data_get($product->shop->settings, 'catalog.product_follow_master');

        if ($hasMaster && $forceFollowMasterProduct) {
            $warningText[] = __('This shop has enabled the Product force follow master setting. Updates made on master will overwrite local changes');
        }

        if (!empty($warningText)) {
            $warning = [
                'type'  => 'warning',
                'title' => __('Important'),
                'text'  => implode('<br>', $warningText),
                'icon'  => ['fas', 'fa-exclamation-triangle']
            ];
        }

        $iconLinks = [];

        if ($hasMaster) {
            $iconLinks[] = [
                'icon'    => 'fab fa-octopus-deploy',
                'tooltip' => __('Go to Edit Master Product'),
                'route'   => [
                    'name'       => 'grp.masters.master_shops.show.master_products.edit',
                    'parameters' => [
                        'masterShop'    => $product->shop->masterShop->slug,
                        'masterProduct' => $product->masterProduct->slug,
                    ]
                ],
                'color'   => 'rgb(75, 0, 130)'
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
                    'previous' => $this->getPreviousModel($product, $request),
                    'next'     => $this->getNextModel($product, $request),
                ],
                'pageHead'    => [
                    'title'     => __('Edit product'),
                    'model'     => $product->code,
                    'icon'      =>
                        [
                            'icon'  => ['fal', 'fa-cube'],
                            'title' => __('Goods')
                        ],
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                    'iconLinks' => $iconLinks
                ],

                'formData' => [
                    'blueprint' => !$isExternalShop ? $this->getBlueprint($product) : $this->getBlueprintExternal($product),
                    'args'      => [
                        'updateRoute' => [
                            'name'       => !$isExternalShop ? 'grp.models.product.update' : 'grp.models.product.external.update',
                            'parameters' => [
                                'product' => $product->id
                            ]
                        ],
                    ]
                ]

            ]
        );
    }

    public function getBlueprintExternal(Product $product): array
    {
        $packedIn = DB::table('model_has_trade_units')
            ->where('model_type', 'Stock')
            ->whereIn('trade_unit_id', $product->tradeUnits->pluck('id'))
            ->pluck('quantity', 'trade_unit_id')
            ->toArray();

        $tradeUnits = $product->tradeUnits->map(function ($t) use ($packedIn) {
            return array_merge(
                ['quantity' => (int)$t->pivot->quantity],
                ['fraction' => $t->pivot->quantity / $packedIn[$t->id]],
                ['packed_in' => $packedIn[$t->id]],
                ['pick_fractional' => riseDivisor(divideWithRemainder(findSmallestFactors($t->pivot->quantity / $packedIn[$t->id])), $packedIn[$t->id])],
                $t->toArray()
            );
        });

        return array_filter([
            [
                'label'  => __('Trade units'),
                'icon'   => 'fa-light fa-atom',
                'fields' => [
                    'trade_units' => [
                        'label'        => __('Trade units'),
                        'type'         => 'list-selector-trade-unit',
                        'key_quantity' => 'quantity',
                        'withQuantity' => true,
                        'full'         => true,
                        'tabs'         => [
                            [
                                'label'      => __('Recommended'),
                                'routeFetch' => [
                                    'name'       => 'grp.json.trade-units.recommended.under-product',
                                    'parameters' => [
                                        'product' => $product->id,
                                    ],
                                ],
                            ],
                            [
                                'label'      => __('All'),
                                'search'     => true,
                                'routeFetch' => [
                                    'name'       => 'grp.json.trade-units.all.under-product',
                                    'parameters' => [
                                        'product' => $product->id,
                                    ],
                                ],
                            ],
                        ],
                        'value'        => $tradeUnits,
                    ],
                ],
            ],
            // ($product->shop->engine == ShopEngineEnum::FAIRE && $product->state == ProductStateEnum::IN_PROCESS) ? [
            //     'label'  => __('Product State'),
            //     'icon'   => 'fa-light fa-fingerprint',
            //     'fields' => [
            //         'state'        => [
            //             'type'      => 'select',
            //             'label'     => __('Set Product State'),
            //             'value'     => $product->state->value,
            //             'options'   => Arr::except(ProductStateEnum::asOption(), [ProductStateEnum::DISCONTINUED->value, ProductStateEnum::DISCONTINUING->value]),
            //             'mode'      => 'single',
            //             'required'  => true,
            //         ],
            //     ]
            // ] : null,
        ]);
    }

    /**
     * @throws \Exception
     */
    public function getBlueprint(Product $product): array
    {
        $barcodes  = $product->tradeUnits->pluck('barcode')->filter()->unique();
        $languages = [$product->shop->language_id => LanguageResource::make($product->shop->language)->resolve()];

        $canEditNotForSale = true;
        if ($product->masterProduct && !$product->masterProduct->is_for_sale) {
            $canEditNotForSale = false;
        }
        if ($canEditNotForSale) {
            foreach ($product->tradeUnits as $tradeUnit) {
                if (!$tradeUnit->is_for_sale) {
                    $canEditNotForSale = false;
                }
            }
        }

        $nameFields = [
            'name'              => $product->masterProduct
                ? [
                    'type'          => 'input_translation',
                    'label'         => __('Name'),
                    'language_from' => 'en',
                    'full'          => true,
                    'main'          => $product->masterProduct->name,
                    'languages'     => $languages,
                    'mode'          => 'single',
                    'value'         => $product->name,
                    'reviewed'      => $product->is_name_reviewed,
                    'information'   => __('This will displayed as H1 in the product page on website and in orders and invoices.'),
                ]
                : [
                    'type'        => 'input',
                    'label'       => __('Name'),
                    'information' => __('This will displayed as H1 in the product page on website and in orders and invoices.'),
                    'options'     => [
                        'counter' => true,
                    ],
                    'value'       => $product->name
                ],
            'description'       => $product->masterProduct
                ? [
                    'type'          => 'textEditor_translation',
                    'label'         => __('Description'),
                    'language_from' => 'en',
                    'full'          => true,
                    'main'          => $product->masterProduct->description,
                    'languages'     => $languages,
                    'mode'          => 'single',
                    'value'         => $product->description,
                    'reviewed'      => $product->is_description_reviewed,
                    'information'   => __('This show in product webpage'),
                    'toogle'        => [
                        'heading2',
                        'heading3',
                        'fontSize',
                        'bold',
                        'italic',
                        'underline',
                        'bulletList',
                        "fontFamily",
                        'orderedList',
                        'blockquote',
                        'divider',
                        'alignLeft',
                        'alignRight',
                        "customLink",
                        'alignCenter',
                        'undo',
                        'redo',
                        'highlight',
                        'color',
                        'clear'
                    ],
                ]
                : [
                    'type'        => 'textEditor',
                    'label'       => __('Description'),
                    'information' => __('This show in product webpage'),
                    'options'     => [
                        'counter' => true,
                    ],
                    'toogle'      => [
                        'heading2',
                        'heading3',
                        'fontSize',
                        'bold',
                        'italic',
                        'underline',
                        'bulletList',
                        "fontFamily",
                        'orderedList',
                        'blockquote',
                        'divider',
                        'alignLeft',
                        'alignRight',
                        "customLink",
                        'alignCenter',
                        'undo',
                        'redo',
                        'highlight',
                        'color',
                        'clear'
                    ],
                    'value'       => $product->description
                ],
            'description_extra' => $product->masterProduct
                ? [
                    'type'          => 'textEditor_translation',
                    'label'         => __('Extra description'),
                    'language_from' => 'en',
                    'full'          => true,
                    'main'          => $product->masterProduct->description_extra,
                    'languages'     => $languages,
                    'mode'          => 'single',
                    'value'         => $product->description_extra,
                    'reviewed'      => $product->is_description_extra_reviewed,
                    'information'   => __('This above product specification in product webpage'),
                    'toogle'        => [
                        'heading2',
                        'heading3',
                        'fontSize',
                        'bold',
                        'italic',
                        'underline',
                        'bulletList',
                        "fontFamily",
                        'orderedList',
                        'blockquote',
                        'divider',
                        'alignLeft',
                        'alignRight',
                        "customLink",
                        'alignCenter',
                        'undo',
                        'redo',
                        'highlight',
                        'color',
                        'clear'
                    ],

                ]
                : [
                    'type'        => 'textEditor',
                    'label'       => __('Extra description'),
                    'information' => __('This above product specification in product webpage'),
                    'options'     => [
                        'counter' => true,
                    ],
                    'value'       => $product->description_extra,
                    'toogle'      => [
                        'heading2',
                        'heading3',
                        'fontSize',
                        'bold',
                        'italic',
                        'underline',
                        'bulletList',
                        "fontFamily",
                        'orderedList',
                        'blockquote',
                        'divider',
                        'alignLeft',
                        'alignRight',
                        "customLink",
                        'alignCenter',
                        'undo',
                        'redo',
                        'highlight',
                        'color',
                        'clear'
                    ],
                ]
        ];

        if ($product->webpage) {
            $webpage           = $product->webpage;
            $webpageNameFields = [
                // for now, we're forcing the breadcrumbs to show product code so no need for this
                //                'webpage_breadcrumb_label' => [
                //                    'type'        => 'input',
                //                    'label'       => __('Breadcrumb label').' ('.__('Optional').')',
                //                    'information' => __('To be used for the breadcrumbs, will use Meta Title if missing'),
                //                    'options'     => [
                //                        'counter' => true,
                //                    ],
                //                    'value'       => $webpage->breadcrumb_label,
                //                ],
                'webpage_title'       => [
                    'type'        => 'input',
                    'label'       => __('Meta Title').' (& '.__('Browser title').')',
                    'information' => __('This will be used as the title displayed in the browser, meta title for SEO, and the search feature'),
                    'options'     => [
                        'counter' => true,
                    ],
                    'value'       => $webpage->title,
                ],
                'webpage_description' => [
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
                            'bind'     => [
                                'minFractionDigits' => 0,
                                'maxFractionDigits' => 2,
                            ],
                            'value'    => $product->price,
                        ],
                        'rrp'              => [
                            'type'     => 'input_number',
                            'label'    => __('RRP'),
                            'required' => true,
                            'bind'     => [
                                'minFractionDigits' => 0,
                                'maxFractionDigits' => 2,
                            ],
                            'value'    => $product->rrp,
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

                $canEditNotForSale
                    ? [
                    'label'  => __('Sale Status'),
                    'icon'   => 'fal fa-cart-arrow-down',
                    'fields' => [
                        'is_for_sale' => [
                            'type'  => 'toggle',
                            'label' => __('For Sale'),
                            'value' => $product->is_for_sale,
                        ],
                    ],
                ] : [],

            ]
        );
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

}
