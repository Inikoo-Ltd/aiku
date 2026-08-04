<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Masters\MasterAsset\GetMasterAssetAnomalies;
use App\Actions\Masters\MasterAsset\WithMasterProductSubNavigation;
use App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate;
use App\Actions\OrgAction;
use App\Actions\Traits\WithUnitsChangeConfirmation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The composition triangle in one screen: what the product physically is (its trade units),
 * how each organisation's warehouse packs them, and what that implies for the price. It is
 * too much command and control for a section of the general edit form, so it lives alone.
 */
class EditMasterProductComposition extends OrgAction
{
    use WithUnitsChangeConfirmation;
    use WithMasterProductSubNavigation;

    public function handle(MasterAsset $masterAsset): MasterAsset
    {
        return $masterAsset;
    }

    public function asController(MasterShop $masterShop, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisationFromGroup($masterShop->group, $request);

        return $this->handle($masterProduct);
    }

    public function htmlResponse(MasterAsset $masterAsset, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/ProductComposition',
            [
                'title'       => __('Composition').': '.$masterAsset->code,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $masterAsset,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'model'   => __('Composition & packing'),
                    'title'   => $masterAsset->code,
                    'icon'    => [
                        'icon'  => ['fal', 'fa-atom'],
                        'title' => __('Composition'),
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.masters.master_shops.show.master_products.show',
                                'parameters' => [
                                    'masterShop'    => $masterAsset->masterShop->slug,
                                    'masterProduct' => $masterAsset->slug,
                                ]
                            ]
                        ]
                    ],
                    'subNavigation' => $this->getMasterProductsSubNavigation($masterAsset),
                ],
                'anomalies' => $this->getAnomalies($masterAsset),
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
     * @return array{items: list<array{product_id: int, shop_code: string, shop_slug: string, issues: list<string>, ignored_issues: list<string>}>, fixRoute: array{name: string, parameters: array{masterAsset: int}, method: string}, killRebelRoute: array{name: string, parameters: array{masterAsset: int}, method: string}}|null
     */
    public function getAnomalies(MasterAsset $masterProduct): ?array
    {
        $anomalies = GetMasterAssetAnomalies::run($masterProduct);
        if (!$anomalies) {
            return null;
        }

        return [
            'items'    => array_values($anomalies),
            'fixRoute' => [
                'name'       => 'grp.models.master_asset.fix_anomalies',
                'parameters' => [
                    'masterAsset' => $masterProduct->id
                ],
                'method'     => 'post',
            ],
            'killRebelRoute' => [
                'name'       => 'grp.models.master_asset.kill_rebel',
                'parameters' => [
                    'masterAsset' => $masterProduct->id
                ],
                'method'     => 'post',
            ],
        ];
    }

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
                    'quantity'         => (int)$quantity,
                    'packed_in'        => $packedInQuantity,
                    'fraction'         => $fraction,
                    'pick_fractional'  => riseDivisor(divideWithRemainder(findSmallestFactors($fraction)), $packedInQuantity),
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

        $pricesUpdateRoute = [
            'name'       => 'grp.models.master_asset.prices.update',
            'parameters' => [
                'masterAsset' => $masterProduct->id
            ]
        ];

        return [
            [
                'label'  => __('Trade units'),
                'icon'   => 'fa-light fa-atom',
                'fields' => [
                    'trade_units' => [
                        'label'            => __('Trade units'),
                        'saveConfirmation' => $this->getUnitsChangeConfirmation($masterProduct),
                        'priceContext'     => [
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
                        'value' => $tradeUnits,
                    ],
                ],
            ],
            [
                'label'  => __('Pricing'),
                'icon'   => 'fa-light fa-money-bill',
                'fields' => [
                    'master_prices' => [
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
                    ],
                    'master_rrps' => [
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
                    ],
                ]
            ],
        ];
    }

    public function getBreadcrumbs(MasterAsset $masterAsset, string $routeName, array $routeParameters): array
    {
        return ShowMasterProduct::make()->getBreadcrumbs(
            masterAsset: $masterAsset,
            routeName: preg_replace('/composition$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Composition').')'
        );
    }
}
