<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Jul 2026 10:35:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Enums\Inventory\OrgStockMovement\OrgStockMovementClassEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class GetLocationOrgStockQuantity
{
    use AsAction;

    public function handle(OrgStock $orgStock, Location $location, ?Carbon $date = null): float
    {
        if (!$date) {
            $date = now()->endOfDay();
        }

        $lastHelper = OrgStockMovement::on('aiku_no_sticky')->select(['audited_quantity', 'date'])
            ->where('org_stock_id', $orgStock->id)
            ->where('location_id', $location->id)
            ->where('class', OrgStockMovementClassEnum::HELPER)
            ->where('date', '<=', $date->copy()->format('Y-m-d H:i:s.u'))->orderBy('date', 'desc')->first();

        $seedQuantity = $lastHelper?->audited_quantity ?? 0;

        $query = OrgStockMovement::on('aiku_no_sticky')->where('org_stock_id', $orgStock->id)
            ->where('location_id', $location->id)
            ->where('class', OrgStockMovementClassEnum::MOVEMENT)
            ->where('date', '<=', $date->copy()->format('Y-m-d H:i:s.u'));

        if ($lastHelper) {
            $query->where('date', '>', Carbon::parse($lastHelper->date)->format('Y-m-d H:i:s.u'));
        }

        $sumMovements = $query->sum('quantity');

        return (float)($seedQuantity + $sumMovements);
    }

}
