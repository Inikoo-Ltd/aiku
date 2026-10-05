<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Models\Procurement\OrgPartner;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Sister companies sell to each other at what the stock cost the seller to land: its FIFO value
 * per SKO from the latest daily stock history (last purchase price when FIFO is missing), in the
 * seller's currency. The manufacturing hub keeps selling at its list price with the buyer's discount.
 *
 * A FIFO far from the seller's supplier cost is a costing error (units booked as SKOs, a carton
 * price on a SKO), not a cost, so outside that band the supplier cost is charged instead.
 */
class GetPartnerLandedCost
{
    use AsObject;

    public const float MIN_SUPPLIER_COST_RATIO = 0.25;

    public const float MAX_SUPPLIER_COST_RATIO = 3;

    public static function appliesTo(OrgPartner $orgPartner): bool
    {
        return !$orgPartner->partner->is_manufacturing_hub;
    }

    public static function perSkoSql(string $sellerOrgStockIdExpression): string
    {
        return '(select '.self::guardedCostSql('coalesce(landed.fifo_per_sku, landed.lpp_per_sku)', 'seller.current_supplier_sku_cost')."
            from org_stock_histories landed
            join org_stocks seller on seller.id = landed.org_stock_id
            where landed.org_stock_id = $sellerOrgStockIdExpression
            order by landed.date desc
            limit 1)";
    }

    private static function guardedCostSql(string $cost, string $supplierCost): string
    {
        return "case when $supplierCost > 0 and ($cost < $supplierCost * ".self::MIN_SUPPLIER_COST_RATIO." or $cost > $supplierCost * ".self::MAX_SUPPLIER_COST_RATIO.")
            then $supplierCost else $cost end";
    }

    /**
     * @param  array<int, int>  $sellerOrgStockIds
     * @return array<int, float> landed cost per SKO keyed by seller org stock id, only those that have one
     */
    public function handle(array $sellerOrgStockIds): array
    {
        if (!$sellerOrgStockIds) {
            return [];
        }

        return DB::table('org_stock_histories')
            ->join('org_stocks', 'org_stocks.id', '=', 'org_stock_histories.org_stock_id')
            ->selectRaw('distinct on (org_stock_histories.org_stock_id) org_stock_histories.org_stock_id, '.self::guardedCostSql('coalesce(org_stock_histories.fifo_per_sku, org_stock_histories.lpp_per_sku)', 'org_stocks.current_supplier_sku_cost').' as cost')
            ->whereIn('org_stock_histories.org_stock_id', $sellerOrgStockIds)
            ->orderBy('org_stock_histories.org_stock_id')
            ->orderByDesc('org_stock_histories.date')
            ->get()
            ->filter(fn ($row) => $row->cost !== null && (float) $row->cost > 0)
            ->mapWithKeys(fn ($row) => [(int) $row->org_stock_id => (float) $row->cost])
            ->all();
    }
}
