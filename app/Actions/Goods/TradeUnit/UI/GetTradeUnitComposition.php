<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\UI;

use App\Models\Goods\TradeUnit;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The triangle seen from its centre: which SKUs pack this trade unit (and how each
 * organisation's warehouse actually packs it), and which products and masters sell it.
 * Each row links to the composition page where that leg is edited; SKOs made in-house
 * without an artefact (recipe) carry the routes to create or link one.
 */
class GetTradeUnitComposition
{
    use AsObject;

    public function handle(TradeUnit $tradeUnit): array
    {
        $stocks = DB::table('model_has_trade_units')
            ->join('stocks', 'stocks.id', '=', 'model_has_trade_units.model_id')
            ->where('model_has_trade_units.model_type', 'Stock')
            ->where('model_has_trade_units.trade_unit_id', $tradeUnit->id)
            ->whereNull('stocks.deleted_at')
            ->select(['stocks.slug', 'stocks.code', 'model_has_trade_units.quantity'])
            ->orderBy('stocks.code')
            ->get()
            ->map(fn ($stock) => [
                'code'     => $stock->code,
                'quantity' => (float) $stock->quantity,
                'route'    => [
                    'name'       => 'grp.goods.stocks.composition',
                    'parameters' => ['stock' => $stock->slug],
                ],
            ])->values()->all();

        $orgStocks = DB::table('model_has_trade_units')
            ->join('org_stocks', 'org_stocks.id', '=', 'model_has_trade_units.model_id')
            ->join('organisations', 'organisations.id', '=', 'org_stocks.organisation_id')
            ->where('model_has_trade_units.model_type', 'OrgStock')
            ->where('model_has_trade_units.trade_unit_id', $tradeUnit->id)
            ->whereNull('org_stocks.deleted_at')
            ->select([
                'org_stocks.id',
                'organisations.code as org_code',
                'organisations.slug as organisation_slug',
                'org_stocks.organisation_id',
                'org_stocks.code',
                'org_stocks.is_made_in_house',
                'model_has_trade_units.quantity',
            ])
            ->orderBy('organisations.code')
            ->get();

        $artefactsByOrgStock = DB::table('artefacts')
            ->join('productions', 'productions.id', '=', 'artefacts.production_id')
            ->whereIn('artefacts.org_stock_id', $orgStocks->pluck('id'))
            ->whereNull('artefacts.deleted_at')
            ->orderBy('artefacts.code')
            ->get(['artefacts.org_stock_id', 'artefacts.code', 'artefacts.slug', 'productions.slug as production_slug'])
            ->groupBy('org_stock_id');

        $productionByOrganisation = DB::table('productions')
            ->whereIn('organisation_id', $orgStocks->pluck('organisation_id')->unique())
            ->orderBy('id')
            ->get(['id', 'slug', 'organisation_id'])
            ->unique('organisation_id')
            ->keyBy('organisation_id');

        $orgStocks = $orgStocks->map(function ($orgStock) use ($artefactsByOrgStock, $productionByOrganisation, $tradeUnit) {
            $production = $productionByOrganisation->get($orgStock->organisation_id);
            $artefacts  = $artefactsByOrgStock->get($orgStock->id, collect());

            return [
                'id'               => $orgStock->id,
                'org_code'         => $orgStock->org_code,
                'code'             => $orgStock->code,
                'quantity'         => (float) $orgStock->quantity,
                'is_made_in_house' => (bool) $orgStock->is_made_in_house,
                'artefacts'        => $artefacts->map(fn ($artefact) => [
                    'code'  => $artefact->code,
                    'route' => [
                        'name'       => 'grp.org.productions.show.crafts.artefacts.show',
                        'parameters' => [$orgStock->organisation_slug, $artefact->production_slug, $artefact->slug],
                    ],
                ])->values()->all(),
                'missing_artefact' => $orgStock->is_made_in_house && $artefacts->isEmpty() && $production
                    ? [
                        'create_route' => [
                            'name'       => 'grp.org.productions.show.crafts.artefacts.create',
                            'parameters' => [
                                'organisation' => $orgStock->organisation_slug,
                                'production'   => $production->slug,
                                'org_stock'    => $orgStock->id,
                                'trade_unit'   => $tradeUnit->id,
                            ],
                        ],
                        'artefacts_route' => [
                            'name'       => 'grp.json.production.artefacts.index',
                            'parameters' => ['production' => $production->id],
                        ],
                        'link_route' => [
                            'name'       => 'grp.models.production.artefacts.update',
                            'parameters' => ['production' => $production->id],
                        ],
                        'link_data' => [
                            'org_stock_id'  => $orgStock->id,
                            'trade_unit_id' => $tradeUnit->id,
                        ],
                    ]
                    : null,
            ];
        })->values()->all();

        $masterProducts = DB::table('model_has_trade_units')
            ->join('master_assets', 'master_assets.id', '=', 'model_has_trade_units.model_id')
            ->join('master_shops', 'master_shops.id', '=', 'master_assets.master_shop_id')
            ->where('model_has_trade_units.model_type', 'MasterAsset')
            ->where('model_has_trade_units.trade_unit_id', $tradeUnit->id)
            ->whereNull('master_assets.deleted_at')
            ->select(['master_assets.slug', 'master_assets.code', 'master_shops.slug as master_shop_slug', 'model_has_trade_units.quantity'])
            ->orderBy('master_assets.code')
            ->get()
            ->map(fn ($masterAsset) => [
                'code'     => $masterAsset->code,
                'quantity' => (float) $masterAsset->quantity,
                'route'    => [
                    'name'       => 'grp.masters.master_shops.show.master_products.composition',
                    'parameters' => [
                        'masterShop'    => $masterAsset->master_shop_slug,
                        'masterProduct' => $masterAsset->slug,
                    ],
                ],
            ])->values()->all();

        $products = DB::table('model_has_trade_units')
            ->join('products', 'products.id', '=', 'model_has_trade_units.model_id')
            ->join('shops', 'shops.id', '=', 'products.shop_id')
            ->join('organisations', 'organisations.id', '=', 'products.organisation_id')
            ->where('model_has_trade_units.model_type', 'Product')
            ->where('model_has_trade_units.trade_unit_id', $tradeUnit->id)
            ->whereNull('products.deleted_at')
            ->where('products.is_for_sale', true)
            ->select([
                'products.slug',
                'products.code',
                'shops.code as shop_code',
                'shops.slug as shop_slug',
                'organisations.slug as organisation_slug',
                'model_has_trade_units.quantity',
            ])
            ->orderBy('products.code')
            ->get()
            ->map(fn ($product) => [
                'code'      => $product->code,
                'shop_code' => $product->shop_code,
                'quantity'  => (float) $product->quantity,
                'route'     => [
                    'name'       => 'grp.org.shops.show.catalogue.products.all_products.composition',
                    'parameters' => [
                        'organisation' => $product->organisation_slug,
                        'shop'         => $product->shop_slug,
                        'product'      => $product->slug,
                    ],
                ],
            ])->values()->all();

        return [
            'stocks'          => $stocks,
            'org_stocks'      => $orgStocks,
            'master_products' => $masterProducts,
            'products'        => $products,
        ];
    }
}
