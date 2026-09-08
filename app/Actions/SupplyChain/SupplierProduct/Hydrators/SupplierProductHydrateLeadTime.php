<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;

class SupplierProductHydrateLeadTime implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:supplier-product-lead-time {--s|slugs=}';

    public function __construct()
    {
        $this->model = SupplierProduct::class;
    }

    public function getJobUniqueId(SupplierProduct $supplierProduct): string
    {
        return $supplierProduct->id;
    }

    public function handle(SupplierProduct $supplierProduct): void
    {
        $measurement = DB::table('purchase_order_transactions')
            ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
            ->join('purchase_order_stock_delivery', 'purchase_order_stock_delivery.purchase_order_id', 'purchase_orders.id')
            ->join('stock_deliveries', 'stock_deliveries.id', 'purchase_order_stock_delivery.stock_delivery_id')
            ->where('purchase_order_transactions.supplier_product_id', $supplierProduct->id)
            ->whereNull('purchase_orders.deleted_at')
            ->whereNotNull('purchase_orders.submitted_at')
            ->where('purchase_orders.submitted_at', '>=', now()->subMonths(12))
            ->whereRaw('coalesce(stock_deliveries.booked_in_at, stock_deliveries.placed_at) is not null')
            ->selectRaw('avg(extract(epoch from coalesce(stock_deliveries.booked_in_at, stock_deliveries.placed_at) - purchase_orders.submitted_at) / 86400) as avg_days, count(*) as samples')
            ->first();

        $samples = (int) $measurement->samples;

        $supplierProduct->update([
            'measured_lead_time_days' => $samples >= 3 ? max(1, (int) round($measurement->avg_days)) : null,
            'lead_time_samples'       => $samples,
        ]);
    }
}
