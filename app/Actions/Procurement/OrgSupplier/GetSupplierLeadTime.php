<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier;

use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Models\Procurement\OrgSupplier;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetSupplierLeadTime
{
    use AsObject;

    public const DEFAULT_DAYS = 14;

    /**
     * Independent suppliers carry their lead time on the product, not on the relationship:
     * supplier_products.measured_lead_time_days is hydrated from booked-in deliveries and
     * estimated_lead_time_days is the human guess. The headline is the median of what each
     * product says, so it can never be edited here.
     *
     * @return array{days: int, source: 'measured'|'estimate', samples: int, measured_products: int, products: int}
     */
    public function handle(OrgSupplier $orgSupplier): array
    {
        $row = DB::table('org_supplier_products as p')
            ->join('supplier_products as sp', 'sp.id', 'p.supplier_product_id')
            ->where('p.org_supplier_id', $orgSupplier->id)
            ->where('sp.supplier_id', $orgSupplier->supplier_id)
            ->where('p.is_available', true)
            ->where('p.state', OrgSupplierProductStateEnum::ACTIVE->value)
            ->whereNull('sp.deleted_at')
            ->selectRaw('count(*) as products,
                count(*) filter (where sp.measured_lead_time_days is not null) as measured_products,
                coalesce(sum(sp.lead_time_samples), 0) as samples,
                percentile_cont(0.5) within group (
                    order by coalesce(sp.measured_lead_time_days, sp.estimated_lead_time_days)
                ) as median_days')
            ->first();

        $measuredProducts = (int) $row->measured_products;

        return [
            'days'              => $row->median_days !== null
                ? max(1, (int) round((float) $row->median_days))
                : self::DEFAULT_DAYS,
            'source'            => $measuredProducts > 0 ? 'measured' : 'estimate',
            'samples'           => (int) $row->samples,
            'measured_products' => $measuredProducts,
            'products'          => (int) $row->products,
        ];
    }
}
