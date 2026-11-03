<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Nov 2026 14:45:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Warehouse\UI;

use App\Actions\Inventory\LocationOrgStock\DisassociateEmptyLocationOrgStocks;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWarehouseEmptyLocationsPanel
{
    use AsObject;

    public function handle(Warehouse $warehouse, bool $canEdit): array
    {
        $lastChange = $warehouse->audits()
            ->whereNotNull('new_values->settings.auto_disassociate_empty_locations')
            ->with('user')
            ->latest('id')
            ->first();

        return [
            'enabled'          => $warehouse->isAutoDisassociatingEmptyLocations(),
            'can_edit'         => $canEdit,
            'update_route'     => [
                'name'       => 'grp.models.warehouse.auto_disassociate_empty_locations.update',
                'parameters' => ['warehouse' => $warehouse->id],
            ],
            'last_change'      => $lastChange ? [
                'by' => $lastChange->user?->contact_name ?? $lastChange->user?->username,
                'at' => $lastChange->created_at,
            ] : null,
            'kept_locations'   => $warehouse->locations()
                ->where('keep_empty_org_stocks', true)
                ->orderBy('code')
                ->get(['slug', 'code']),
            'to_remove'        => DisassociateEmptyLocationOrgStocks::candidatesQuery($warehouse)->count(),
            'max_per_run'      => DisassociateEmptyLocationOrgStocks::MAX_PER_RUN,
            'removed_last_7_days' => OrgStockMovement::where('warehouse_id', $warehouse->id)
                ->where('type', OrgStockMovementTypeEnum::DISASSOCIATE)
                ->where('reason', OrgStockMovementReasonEnum::EMPTY_LOCATION)
                ->where('date', '>', now()->subDays(7))
                ->count(),
        ];
    }
}
