<?php

/*
 * Author Louis Perez
 * Created on 25-11-2026-14h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Catalogue\Product\GetProductIncomingStock;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterAsset;
use App\Models\Production\Artefact;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Aromatics only: what the recipe materials of one outer cost, split by material, from the artefacts
 * the organisation selling the master shop makes (the Aroma shop is the master shop's only shop).
 * A recipe line costs quantity per artefact unit (one trade unit) times the raw material's unit cost,
 * the same maths production uses to cost intermediates.
 *
 * @phpstan-type MaterialLine array{code: string, name: string, quantity: float, unit_cost: float, cost: float, share: float, next_delivery: array<string, mixed>|null}
 */
class GetMasterAssetMaterialCost
{
    use AsObject;

    /**
     * @return array{source: string, organisation_code: string, currency_code: string, effective_cost: float|null, materials_total: float, rest: float, materials: list<MaterialLine>}|null
     */
    public function handle(MasterAsset $masterAsset): ?array
    {
        $masterShop = $masterAsset->masterShop;
        if (!GetMasterAssetsPartnerPricing::appliesTo($masterShop)) {
            return null;
        }

        $organisation = self::sellerOrganisation($masterAsset);
        if (!$organisation) {
            return null;
        }

        $lines = DB::table('model_has_trade_units as master_trade_units')
            ->join('artefacts', function ($join) use ($organisation) {
                $join->on('artefacts.trade_unit_id', '=', 'master_trade_units.trade_unit_id')
                    ->where('artefacts.organisation_id', $organisation->id)
                    ->whereNull('artefacts.deleted_at');
            })
            ->join('artefacts_manufacture_tasks', 'artefacts_manufacture_tasks.artefact_id', '=', 'artefacts.id')
            ->join('recipe_step_raw_materials', 'recipe_step_raw_materials.artefact_manufacture_task_id', '=', 'artefacts_manufacture_tasks.id')
            ->join('raw_materials', function ($join) {
                $join->on('raw_materials.id', '=', 'recipe_step_raw_materials.raw_material_id')
                    ->whereNull('raw_materials.deleted_at');
            })
            ->where('master_trade_units.model_type', 'MasterAsset')
            ->where('master_trade_units.model_id', $masterAsset->id)
            ->groupBy('raw_materials.id', 'raw_materials.code', 'raw_materials.description', 'raw_materials.unit_cost', 'raw_materials.org_stock_id')
            ->select('raw_materials.id', 'raw_materials.code', 'raw_materials.description', 'raw_materials.unit_cost', 'raw_materials.org_stock_id')
            ->selectRaw('sum(master_trade_units.quantity * recipe_step_raw_materials.quantity_per_unit) as quantity')
            ->get();

        $source = 'recipe';
        if ($lines->isEmpty()) {
            $source = 'stock';
            $lines  = $this->stockLines($masterAsset, $organisation);
        }
        if ($lines->isEmpty()) {
            $source = 'none';
        }

        if ($source === 'none' && $masterAsset->effective_cost === null) {
            return null;
        }

        $groupCurrency = $masterAsset->group->currency;
        $rate          = $organisation->currency_id === $groupCurrency->id ? 1.0 : (float) (GetCurrencyExchange::run($organisation->currency, $groupCurrency) ?: 1.0);

        $nextDeliveries = collect(GetProductIncomingStock::make()->forOrgStocks($lines->pluck('org_stock_id')->filter()->unique()->values()->all()))
            ->groupBy('org_stock_id')
            ->map(fn ($deliveries) => $deliveries->first());

        $materials = $lines->map(function ($line) use ($rate, $nextDeliveries) {
            $unitCost = (float) $line->unit_cost * $rate;
            $next     = $line->org_stock_id ? $nextDeliveries->get($line->org_stock_id) : null;

            return [
                'code'          => $line->code,
                'name'          => $line->description,
                'quantity'      => round((float) $line->quantity, 4),
                'unit_cost'     => round($unitCost, 4),
                'cost'          => round((float) $line->quantity * $unitCost, 4),
                'next_delivery' => $next ? [
                    'eta'         => $next['eta'],
                    'is_estimate' => (bool) ($next['is_estimate'] ?? false),
                    'is_overdue'  => (bool) ($next['is_overdue'] ?? false),
                    'reference'   => $next['reference'],
                    'supplier'    => $next['supplier_name'],
                    'quantity'    => $next['quantity'],
                ] : null,
            ];
        })->sortByDesc('cost')->values();

        $materialsTotal = (float) $materials->sum('cost');
        $effectiveCost  = $masterAsset->effective_cost !== null ? round((float) $masterAsset->effective_cost, 4) : null;
        $barTotal       = max($materialsTotal, $effectiveCost ?? 0);

        return [
            'source'            => $source,
            'organisation_code' => $organisation->code,
            'currency_code'     => $groupCurrency->code,
            'effective_cost'    => $effectiveCost,
            'materials_total'   => round($materialsTotal, 4),
            'rest'              => round(max(0, ($effectiveCost ?? 0) - $materialsTotal), 4),
            'materials'         => $materials->map(fn (array $material) => [
                ...$material,
                'share' => $barTotal > 0 ? round($material['cost'] / $barTotal * 100, 2) : 0,
            ])->all(),
        ];
    }

    /**
     * The organisation selling the Aromatics master shop: the Aroma shop is the master shop's only shop.
     */
    public static function sellerOrganisation(MasterAsset $masterAsset): ?Organisation
    {
        if (!GetMasterAssetsPartnerPricing::appliesTo($masterAsset->masterShop)) {
            return null;
        }

        return Organisation::find(Shop::where('master_shop_id', $masterAsset->master_shop_id)->value('organisation_id'));
    }

    /**
     * Page-head icon link to the artefact the seller makes for this master's trade units, or null when it makes none.
     *
     * @return array{icon: string, tooltip: string, route: array{name: string, parameters: array<string, string>}}|null
     */
    public static function artefactIconLink(MasterAsset $masterAsset): ?array
    {
        $organisation = self::sellerOrganisation($masterAsset);
        if (!$organisation) {
            return null;
        }

        $artefact = Artefact::query()
            ->where('organisation_id', $organisation->id)
            ->whereIn('trade_unit_id', DB::table('model_has_trade_units')
                ->where('model_type', 'MasterAsset')
                ->where('model_id', $masterAsset->id)
                ->select('trade_unit_id'))
            ->orderBy('id')
            ->first();

        $production = $artefact ? Production::find($artefact->production_id) : null;
        if (!$production) {
            return null;
        }

        return [
            'icon'    => 'fal fa-hamsa',
            'tooltip' => __('Go to artefact :code', ['code' => $artefact->code]),
            'route'   => [
                'name'       => 'grp.org.productions.show.crafts.artefacts.show',
                'parameters' => [
                    'organisation' => $organisation->slug,
                    'production'   => $production->slug,
                    'artefact'     => $artefact->slug,
                ],
            ],
        ];
    }

    /**
     * Without a recipe, what one outer costs in the seller's own stock: each SKO of the master's trade units,
     * at its last purchase price (or current supplier cost, or stock value), in SKOs per outer.
     */
    private function stockLines(MasterAsset $masterAsset, Organisation $organisation): \Illuminate\Support\Collection
    {
        return DB::table('model_has_trade_units as master_trade_units')
            ->join('model_has_trade_units as stock_trade_units', function ($join) {
                $join->on('stock_trade_units.trade_unit_id', '=', 'master_trade_units.trade_unit_id')
                    ->where('stock_trade_units.model_type', 'OrgStock');
            })
            ->join('org_stocks', function ($join) use ($organisation) {
                $join->on('org_stocks.id', '=', 'stock_trade_units.model_id')
                    ->where('org_stocks.organisation_id', $organisation->id);
            })
            ->where('master_trade_units.model_type', 'MasterAsset')
            ->where('master_trade_units.model_id', $masterAsset->id)
            ->groupBy('org_stocks.id', 'org_stocks.code', 'org_stocks.name', 'org_stocks.lpp_per_sku', 'org_stocks.current_supplier_sku_cost', 'org_stocks.sku_value', 'org_stocks.packed_in')
            ->select('org_stocks.id as org_stock_id', 'org_stocks.code', 'org_stocks.name as description')
            ->selectRaw('COALESCE(NULLIF(org_stocks.lpp_per_sku, 0), NULLIF(org_stocks.current_supplier_sku_cost, 0), NULLIF(org_stocks.sku_value, 0), 0) as unit_cost')
            ->selectRaw('sum(master_trade_units.quantity) / COALESCE(NULLIF(org_stocks.packed_in, 0), 1) as quantity')
            ->get()
            ->filter(fn ($line) => (float) $line->unit_cost > 0)
            ->values();
    }
}
