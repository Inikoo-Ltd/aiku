<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:09:31 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Traits\WithUnitsChangeConfirmation;
use App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Masters\MasterFamiliesResource;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Currency;
use App\Models\Helpers\TaxCategory;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;

class EditMasterProduct extends OrgAction
{
    use WithMasterProductNavigation;
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
        $packedIn = $masterProduct->getStockPackedInByTradeUnit();

        /*
         * A master covers several organisations and each warehouse can pack the same trade
         * unit differently. The editor shows every organisation's packed_in and lets it be
         * edited in place, because the org stock is the physical reality.
         */
        $packedInByOrg = DB::table('model_has_trade_units')
            ->join('org_stocks', 'org_stocks.id', '=', 'model_has_trade_units.model_id')
            ->join('organisations', 'organisations.id', '=', 'org_stocks.organisation_id')
            ->where('model_has_trade_units.model_type', 'OrgStock')
            ->whereIn('model_has_trade_units.trade_unit_id', $masterProduct->tradeUnits->pluck('id'))
            ->whereNull('org_stocks.deleted_at')
            ->select([
                'model_has_trade_units.trade_unit_id',
                'org_stocks.id as org_stock_id',
                'organisations.code as org_code',
                'model_has_trade_units.quantity',
            ])
            ->orderBy('organisations.code')
            ->get()
            ->groupBy('trade_unit_id');

        $tradeUnits = $masterProduct->tradeUnits->map(function (TradeUnit $tradeUnit) use ($packedIn, $packedInByOrg) {
            /** @var MorphPivot $pivot */
            $pivot            = $tradeUnit->getRelationValue('pivot');
            $quantity         = $pivot->getAttribute('quantity');
            $packedInQuantity = Arr::get($packedIn, $tradeUnit->id, 1);
            $fraction         = $quantity / $packedInQuantity;

            return array_merge(
                [
                    'quantity'        => (int)$quantity,
                    'packed_in'       => $packedInQuantity,
                    'fraction'        => $fraction,
                    'pick_fractional' => riseDivisor(divideWithRemainder(findSmallestFactors($fraction)), $packedInQuantity),
                    'packed_in_by_org' => ($packedInByOrg->get($tradeUnit->id) ?? collect())->map(fn ($orgStockPivot) => [
                        'org_stock_id' => $orgStockPivot->org_stock_id,
                        'org_code'     => $orgStockPivot->org_code,
                        'packed_in'    => (float) $orgStockPivot->quantity,
                    ])->values()->all(),
                ],
                $tradeUnit->toArray()
            );
        });

        $currenciesRate = GetMasterShopCurrenciesRate::run($masterProduct->masterShop);

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

        $taxCategoryOptions = TaxCategory::orderBy('name')->get()->map(fn (TaxCategory $taxCategory) => [
            'value' => $taxCategory->id,
            'label' => $taxCategory->name.' ('.percentage($taxCategory->rate, 1).') #'.$taxCategory->id,
        ])->all();

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
            [
                'label'  => __('Id'),
                'icon'   => 'fa-light fa-fingerprint',
                'fields' => [
                    'code' => [
                        'type'  => 'input',
                        'label' => __('Code'),
                        'value' => $masterProduct->code
                    ],
                ]
            ],
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
                    'tax_category' => [
                        'type'     => 'dynamic_list',
                        'label'    => __('Tax overrides'),
                        'value'    => $this->getTaxCategoryRows($masterProduct),
                        'fields'   => [
                            [
                                'key'         => 'order_tax_category_id',
                                'label'       => __('When the order is taxed at'),
                                'placeholder' => __('Order tax category'),
                                'options'     => $taxCategoryOptions,
                            ],
                            [
                                'key'         => 'tax_category_id',
                                'label'       => __('Tax this product at'),
                                'placeholder' => __('Product tax category'),
                                'options'     => $taxCategoryOptions,
                            ],
                        ],
                        'addLabel' => __('Add tax override'),
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
                                'masterShop'                    => $masterProduct->masterShop->slug,
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
                    'trade_units' => [
                        'label'        => __('Trade units'),
                        'saveConfirmation' => $this->getUnitsChangeConfirmation($masterProduct),
                        'priceContext' => [
                            'price'    => (float) data_get($masterProduct->master_prices, 'EUR.value', $masterProduct->price),
                            'rrp'      => (float) data_get($masterProduct->master_rrps, 'EUR.value', $masterProduct->rrp),
                            'currency' => 'EUR',
                            'units'    => (float) $masterProduct->units,
                        ],
                        'type'         => 'list-selector-trade-unit',
                        'key_quantity' => 'quantity',
                        'withQuantity' => true,
                        'full'         => true,
                        'noSaveButton' => true,
                        'use_confirm'  => true,
                        'is_dropship'  => $masterProduct->masterShop->type == ShopTypeEnum::DROPSHIPPING,
                        'tabs' => array_values(array_filter([
                            $masterProduct->masterFamily ? [
                                'label'      => __('To do'),
                                'routeFetch' => [
                                    'name'       => 'grp.json.master-product-category.recommended-trade-units',
                                    'parameters' => [
                                        'masterProductCategory' => $masterProduct->masterFamily->id,
                                    ],
                                ],
                            ] : null,

                            $masterProduct->masterFamily ? [
                                'label'      => __('Done'),
                                'routeFetch' => [
                                    'name'       => 'grp.json.master-product-category.taken-trade-units',
                                    'parameters' => [
                                        'masterProductCategory' => $masterProduct->masterFamily->id,
                                    ],
                                ],
                            ] : null,

                            [
                                'label'      => __('All'),
                                'search'     => true,
                                'routeFetch' => [
                                    'name' => 'grp.json.master_product_category.all_trade_units',
                                ],
                            ],
                        ])),
                        'value'        => $tradeUnits,
                    ],
                    'master_prices' => $masterPricesField,
                    'master_rrps'   => $masterRRPsField,
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


        ];
    }


    /**
     * `master_assets.tax_category` is stored as order-category-id => override-category-id;
     * the form edits it as a list of rows.
     *
     * @return array<int, array{order_tax_category_id: string, tax_category_id: string}>
     */
    public function getTaxCategoryRows(MasterAsset $masterProduct): array
    {
        $rows = [];
        foreach ($masterProduct->tax_category ?? [] as $orderTaxCategoryId => $taxCategoryId) {
            $rows[] = [
                'order_tax_category_id' => (string)$orderTaxCategoryId,
                'tax_category_id'       => (string)$taxCategoryId,
            ];
        }

        return $rows;
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
