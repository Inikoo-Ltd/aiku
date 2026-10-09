<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reports;

use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The SKOs an organisation sends parcels in, with what one unit weighs and how much was used in the period.
 */
class GetEprShipmentPackaging
{
    use AsAction;

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(Organisation $organisation, Carbon $from, Carbon $to): array
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT os.id, os.code, os.name, pc.material_category, pc.weight_g,
                (SELECT SUM(l.quantity) FROM epr_flow_lines l
                 WHERE l.organisation_id = os.organisation_id AND l.org_stock_id = os.id AND l.activity = 'shipment_packaging' AND l.date BETWEEN ? AND ?) AS used
            FROM org_stocks os
            LEFT JOIN model_has_trade_units mhtu ON mhtu.model_type = 'OrgStock' AND mhtu.model_id = os.id
            LEFT JOIN trade_units tu ON tu.id = mhtu.trade_unit_id
            LEFT JOIN packaging_family_has_components fhc ON fhc.packaging_family_id = tu.packaging_family_id
            LEFT JOIN packaging_components pc ON pc.id = fhc.packaging_component_id
            WHERE os.organisation_id = ? AND os.is_shipment_packaging
            ORDER BY os.code
            SQL,
            [$from->toDateString(), $to->toDateString(), $organisation->id]
        );

        return collect($rows)->map(fn (object $row) => [
            'id'                => $row->id,
            'code'              => $row->code,
            'name'              => $row->name,
            'material_category' => $row->material_category,
            'weight_g'          => $row->weight_g === null ? null : (float)$row->weight_g,
            'used'              => (float)$row->used,
            'kg'                => round((float)$row->used * (float)$row->weight_g / 1000, 1),
        ])->all();
    }
}
