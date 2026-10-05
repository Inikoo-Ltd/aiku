<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Actions\Procurement\OrgSupplier\GetSupplierLeadTime;
use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What a buyer needs on a purchase order line to decide how many to order: cover today, the daily
 * rate and, when TimesFM made it, the weekly forecast so seasonal peaks count, the lead time and the
 * family's overstock edge, the same edges the stock cover dashboard uses.
 */
class GetOrgStockBuyingSignals
{
    use AsObject;

    /**
     * @return array{days: float|null, days_worst_case: float|null, out_of_stock_at: mixed, daily_usage: float|null, weekly_forecast: array<int, float>|null, lead_time_days: int, overstock_days: int}|null
     */
    public function handle(?OrgStock $orgStock, ?SupplierProduct $supplierProduct = null, ?int $partnerLeadTimeDays = null): ?array
    {
        $stats = $orgStock?->stats;
        if (!$stats) {
            return null;
        }

        $weeks = Arr::get($stats->demand_forecast ?? [], 'weeks');

        return [
            'days'            => $stats->days_of_cover === null ? null : (float) $stats->days_of_cover,
            'days_worst_case' => $stats->days_of_cover_pessimistic === null ? null : (float) $stats->days_of_cover_pessimistic,
            'out_of_stock_at' => $stats->predicted_out_of_stock_at,
            'daily_usage'     => $stats->predicted_daily_usage === null ? null : (float) $stats->predicted_daily_usage,
            'weekly_forecast' => $stats->forecast_source === 'timesfm' && is_array($weeks) && $weeks
                ? array_map(fn ($week) => round(max(0.0, (float) ($week[0] ?? 0)), 2), $weeks)
                : null,
            'lead_time_days'  => $partnerLeadTimeDays ?? $this->leadTimeDays($orgStock, $supplierProduct),
            'overstock_days'  => (int) (Arr::get($orgStock->stock?->stockFamily?->data ?? [], 'stock_cover.overstock_days') ?: GetOrganisationStockCoverBuckets::EXCESS_DAYS),
        ];
    }

    private function leadTimeDays(OrgStock $orgStock, ?SupplierProduct $supplierProduct): int
    {
        return (int) ($orgStock->measured_lead_time_days
            ?? $orgStock->estimated_lead_time_days
            ?? $supplierProduct?->measured_lead_time_days
            ?? $supplierProduct?->estimated_lead_time_days
            ?? GetSupplierLeadTime::DEFAULT_DAYS);
    }
}
