<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;

class OrgStockHydrateLeadTime implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:org-stock-lead-time {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = OrgStock::class;
    }

    public function getJobUniqueId(OrgStock $orgStock): string
    {
        return $orgStock->id;
    }

    public function handle(OrgStock $orgStock): void
    {
        $measurement = DB::table('purchase_order_transactions')
            ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
            ->join('purchase_order_stock_delivery', 'purchase_order_stock_delivery.purchase_order_id', 'purchase_orders.id')
            ->join('stock_deliveries', 'stock_deliveries.id', 'purchase_order_stock_delivery.stock_delivery_id')
            ->where('purchase_order_transactions.org_stock_id', $orgStock->id)
            ->whereNull('purchase_orders.deleted_at')
            ->whereNotNull('purchase_orders.submitted_at')
            ->where('purchase_orders.submitted_at', '>=', now()->subMonths(12))
            ->whereRaw('coalesce(stock_deliveries.booked_in_at, stock_deliveries.placed_at) is not null')
            ->selectRaw('avg(extract(epoch from coalesce(stock_deliveries.booked_in_at, stock_deliveries.placed_at) - purchase_orders.submitted_at) / 86400) as avg_days, count(*) as samples')
            ->first();

        $samples = (int) $measurement->samples;

        $orgStock->update([
            'measured_lead_time_days' => $samples >= 3 ? max(1, (int) round($measurement->avg_days)) : null,
            'lead_time_samples'       => $samples,
        ]);
    }
}
