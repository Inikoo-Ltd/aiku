<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:09:31 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Masters\MasterAsset\TaxPresetBasketProgress;
use App\Actions\Traits\WithLineTaxCategories;
use App\Actions\Traits\WithUnitsChangeConfirmation;
use App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Masters\MasterFamiliesResource;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;

class EditMasterProduct extends OrgAction
{
    use WithMasterProductNavigation;
    use WithLineTaxCategories;
    use WithUnitsChangeConfirmation;

    public function handle(MasterAsset $masterAsset): MasterAsset
    {
        return $masterAsset;
    }

    public function asController(MasterShop $masterShop, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisationFromGroup($masterShop->group, $request);

        return $this->handle($masterProduct);
    }

    public function inGroup(MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisationFromGroup($masterProduct->group, $request);

        return $this->handle($masterProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartment(MasterAsset $masterDepartment, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisationFromGroup($masterProduct->group, $request);

        return $this->handle($masterProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisationFromGroup($masterShop->group, $request);

        return $this->handle($masterProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterFamilyInMasterShop(MasterShop $masterShop, MasterProductCategory $masterFamily, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisationFromGroup($masterShop->group, $request);

        return $this->handle($masterProduct);
    }

    /**
     * @throws \Exception
     */
    public function htmlResponse(MasterAsset $masterAsset, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Editing master product').': '.$masterAsset->code,
                'warning'     => $masterAsset->units_review ? [
                    'type'  => 'warning',
                    'title' => __('Units need review'),
                    'text'  => __('This master product has a units mismatch with its shop products (:bucket) — per-unit prices may be wrong, review before editing.', ['bucket' => $masterAsset->units_review]),
                    'icon'  => ['fas', 'fa-exclamation-triangle']
                ] : null,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $masterAsset,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPreviousModel($masterAsset, $request),
                    'next'     => $this->getNextModel($masterAsset, $request),
                ],
                'pageHead'    => [
                    'model'   => __('Editing master product'),
                    'title'   => $masterAsset->code,
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-cube'],
                            'title' => __('Master product'),
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
                    'blueprint' => $this->getBlueprint($masterAsset),
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.master_asset.update',
                            'parameters' => [
                                'masterAsset' => $masterAsset->id
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
    public function getBlueprint(MasterAsset $masterProduct): array
    {
        $masterShop = $masterProduct->masterShop;

        $tradeUnits = $masterProduct->tradeUnits->map(function (TradeUnit $tradeUnit) {
            /** @var MorphPivot $pivot */
            $pivot = $tradeUnit->getRelationValue('pivot');

            return [
                'quantity' => (int) $pivot->getAttribute('quantity'),
                'code'     => $tradeUnit->code,
            ];
        });

        $currenciesRate = GetMasterShopCurrenciesRate::run($masterShop);

        $costs = null;
        if ($masterProduct->effective_cost !== null) {
            $groupCurrency = $masterProduct->group->currency;
            $currencies    = Currency::whereIn('code', $currenciesRate->keys())->get()->keyBy('code');

            $costs = $currenciesRate->map(function ($rate, $currencyCode) use ($masterProduct, $groupCurrency, $currencies) {
                $exchange = GetCurrencyExchange::run($groupCurrency, $currencies[$currencyCode]);

                return $exchange ? round((float) $masterProduct->effective_cost * $exchange, 2) : null;
            });
        }

        $unitsReview = [
            'master'   => $masterProduct->units_review,
            'products' => $masterProduct->products()
                ->whereNotNull('units_review')
                ->join('shops', 'shops.id', 'products.shop_id')
                ->pluck('products.units_review', 'shops.code')
                ->all(),
        ];
        if (!$unitsReview['master'] && !$unitsReview['products']) {
            $unitsReview = null;
        }

        $pricesUpdateRoute = [
            'name'       => 'grp.models.master_asset.prices.update',
            'parameters' => [
                'masterAsset' => $masterProduct->id
            ]
        ];

        /*
         * Price and composition are one decision: changing what the product physically is
         * either scales the price (repackaging) or the composition was wrong and the price
         * already fits. The price fields live in both sections so nobody saves one half.
         */
        $masterPricesField = [
            'type'         => 'multiple_price_currency',
            'label'        => __('Price').' / '.__('Outer'),
            'required'     => true,
            'currencies'   => $currenciesRate,
            'value'        => $masterProduct->master_prices,
            'masterAsset'  => $masterProduct->id,
            'unitsReview'  => $unitsReview,
            'updateRoute'  => $pricesUpdateRoute,
            'noSaveButton' => true,
            'costs'        => $costs,
            'units'        => (float) $masterProduct->units,
            'type_input'   => 'price'
        ];

        $masterRRPsField = [
            'type'              => 'multiple_price_currency',
            'label'             => __('RRP').' / '.__('Unit'),
            'required'          => true,
            'currencies'        => $currenciesRate,
            'value'             => $masterProduct->master_rrps,
            'masterAsset'       => $masterProduct->id,
            'unitsReview'       => $unitsReview,
            'updateRoute'       => $pricesUpdateRoute,
            'noSaveButton'      => true,
            'perUnits'          => (float) $masterProduct->units,
            'counterpartRecord' => $masterProduct->master_prices,
            'type_input'        => 'rrp'
        ];

        return [
            // [
            //     'label'  => __('Id'),
            //     'icon'   => 'fa-light fa-fingerprint',
            //     'fields' => [
            //         'code' => [
            //             'type'  => 'input',
            //             'label' => __('Code'),
            //             'value' => $masterProduct->code
            //         ],
            //     ]
            // ],
            [
                'label'  => __('Name/Description'),
                'icon'   => 'fa-light fa-tag',
                'fields' => [
                    'name'              => [
                        'type'    => 'input',
                        'label'   => __('Name'),
                        'options' => [
                            'counter' => true,
                        ],
                        'value'   => $masterProduct->name
                    ],
                    'description'       => [
                        'type'    => 'textEditor',
                        'label'   => __('Description'),
                        'options' => [
                            'counter' => true,
                        ],
                        'value'   => $masterProduct->description,
                        'toggle'  => [
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
                            "link",
                            'alignCenter',
                            'undo',
                            'redo',
                            'highlight',
                            'color',
                            'clear'
                        ],
                    ],
                    'description_extra' => [
                        'type'    => 'textEditor',
                        'label'   => __('Extra description'),
                        'options' => [
                            'counter' => true,
                        ],
                        'value'   => $masterProduct->description_extra,
                        'toggle'  => [
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
                            "link",
                            'alignCenter',
                            'undo',
                            'redo',
                            'highlight',
                            'color',
                            'clear'
                        ],
                    ],
                ]
            ],
            [
                'label'  => __('Pricing'),
                'icon'   => 'fa-light fa-money-bill',
                'fields' => [
                    'master_prices' => $masterPricesField,
                    'master_rrps'   => $masterRRPsField,
                ]
            ],
            [
                'label'  => __('Properties'),
                'title'  => __('id'),
                'icon'   => 'fa-light fa-fingerprint',
                'fields' => [
                    'unit'    => [
                        'type'  => 'input',
                        'label' => __('Unit label'),
                        'value' => $masterProduct->unit,
                    ],

                ]
            ],
            [
                'label'  => __('Tax'),
                'icon'   => 'fa-light fa-percent',
                'fields' => [
                    'tax_preset' => [
                        'type'             => 'tax_preset',
                        'mode'             => 'card',
                        'columns'          => 1,
                        'valueProp'        => 'value',
                        'label'            => __('Tax treatment'),
                        'options'          => $this->getTaxPresetOptions($masterProduct->tax_category ?? []),
                        'master_asset_id'  => $masterProduct->id,
                        /** Only a sweep still running matters on load; a finished one is history. */
                        'sweep'            => ($sweep = TaxPresetBasketProgress::get($masterProduct)) && $sweep['state'] != 'finished' ? $sweep : null,
                        'affected_baskets' => $affectedBaskets = $this->getTaxChangeAffectedBasketCount($masterProduct),
                        'saveConfirmation' => [
                            'title'       => __('Change the tax treatment?'),
                            'description' => trans_choice(
                                '{0} No open basket holds this product right now. Orders already submitted keep the tax they were sold under.|{1} :count open basket holds this product and will be retaxed. Orders already submitted keep the tax they were sold under.|[2,*] :count open baskets hold this product and will be retaxed. Orders already submitted keep the tax they were sold under.',
                                $affectedBaskets
                            ),
                            'yesLabel'    => __('Yes, change the tax'),
                        ],
                        'value'            => $masterProduct->tax_preset ?? 'custom',
                    ],
                ]
            ],
            [
                'label'  => __('Master family'),
                'icon'   => 'fal fa-folder',
                'fields' => [
                    'master_family_id' => [
                        'type'       => 'select_infinite',
                        'label'      => __('Master family'),
                        'options'    => [
                            $masterProduct->masterFamily ? MasterFamiliesResource::make($masterProduct->masterFamily)->toArray(request()) : []
                        ],
                        'fetchRoute' => [
                            'name'       => 'grp.json.master-family.all-master-family',
                            'parameters' => [
                                'masterShop'                    => $masterShop->slug,
                                'withMasterProductCategoryStat' => true,
                            ]
                        ],
                        'required'   => true,
                        'type_label' => 'families',
                        'valueProp'  => 'id',
                        'labelProp'  => 'code',
                        'value'      => $masterProduct->master_family_id,
                    ]
                ]
            ],

            [
                'label'  => __('Trade units'),
                'icon'   => 'fa-light fa-atom',
                'fields' => [
                    /*
                     * Composition, per-warehouse packing and the price they imply are one
                     * decision with too many controls for this form, so they live on their
                     * own page. This is only the summary and the door.
                     */
                    'composition' => [
                        'type'         => 'button',
                        'noSaveButton' => true,
                        'label'        => $tradeUnits->map(fn ($tradeUnit) => trimDecimalZeros($tradeUnit['quantity']).' × '.$tradeUnit['code'])->implode(', '),
                        'label_button' => __('Edit composition & packing'),
                        'icon'         => 'fal fa-atom',
                        'type_button'  => 'secondary',
                        'route'        => [
                            'name'       => 'grp.masters.master_shops.show.master_products.composition',
                            'parameters' => [
                                'masterShop'    => $masterShop->slug,
                                'masterProduct' => $masterProduct->slug,
                            ]
                        ],
                    ],
                ],
            ],

            $masterProduct->not_for_sale_from_trade_unit
                ? []
                : [
                'label'  => __('Sale status'),
                'icon'   => 'fal fa-cart-arrow-down',
                'fields' => [
                    'is_for_sale' => [
                        'saveConfirmation' => [
                            'description' => __('Changing the sale status of a master product will affect all products linked to it in all shops.'),
                        ],
                        'type'         => 'toggle',
                        'label'        => __('For Sale'),
                        'value'        => $masterProduct->is_for_sale,
                    ],
                ],
            ],
            !$masterProduct->is_single_trade_unit
                ? []
                : [
                'label'  => __('Follow images'),
                'icon'   => 'fal fa-image',
                'fields' => [
                    'follow_trade_unit_media' => [
                        'type'         => 'toggle',
                        'label'        => __('Follow images from trade unit'),
                        'value'        => $masterProduct->follow_trade_unit_media,
                    ],
                ],
            ],
            $masterShop->type == ShopTypeEnum::DROPSHIPPING ? [] : [
                'label'  => __('Offer Details'),
                'icon'   => 'fa-light fa-badge-percent',
                'fields'        => [
                    'is_golden_product' => [
                        'type'          => 'toggle',
                        'label'         => __('Golden Product'),
                        'value'         => $masterProduct->is_golden_product,
                        'information'   => __("Would mark the product as Golden Product, which would apply Gold Reward offer to all siblings in basket when a customer added it"),
                        'warningText'   => __('Modifying this setting would mark the product as Golden Product, which would apply Gold Reward offer to all siblings in basket when a customer added it').'. '.__('Are you sure you want to do this?'),
                        'noSaveButton'    => true,
                        'submitOnConfirm' => true,
                    ],
                ]
            ],
        ];
    }


    public function getBreadcrumbs(MasterAsset $masterAsset, string $routeName, array $routeParameters): array
    {
        return ShowMasterProduct::make()->getBreadcrumbs(
            masterAsset: $masterAsset,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
