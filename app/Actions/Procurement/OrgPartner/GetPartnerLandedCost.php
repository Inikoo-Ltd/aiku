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
 */
class GetPartnerLandedCost
{
    use AsObject;

    public static function appliesTo(OrgPartner $orgPartner): bool
    {
        return !$orgPartner->partner->is_manufacturing_hub;
    }

    public static function perSkoSql(string $sellerOrgStockIdExpression): string
    {
        return "(select coalesce(landed.fifo_per_sku, landed.lpp_per_sku)
            from org_stock_histories landed
            where landed.org_stock_id = $sellerOrgStockIdExpression
            order by landed.date desc
            limit 1)";
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
            ->selectRaw('distinct on (org_stock_id) org_stock_id, coalesce(fifo_per_sku, lpp_per_sku) as cost')
            ->whereIn('org_stock_id', $sellerOrgStockIds)
            ->orderBy('org_stock_id')
            ->orderByDesc('date')
            ->get()
            ->filter(fn ($row) => $row->cost !== null && (float) $row->cost > 0)
            ->mapWithKeys(fn ($row) => [(int) $row->org_stock_id => (float) $row->cost])
            ->all();
    }
}
