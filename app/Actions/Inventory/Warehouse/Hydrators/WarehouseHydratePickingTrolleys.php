<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:15:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Warehouse\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Inventory\Warehouse;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WarehouseHydratePickingTrolleys implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Warehouse $warehouse): string
    {
        return $warehouse->id;
    }


    public function handle(Warehouse $warehouse): void
    {
        $allPickingTrolleys            = $warehouse->pickingTrolleys()->count();
        $currentPickingTrolleys = $warehouse->pickingTrolleys()->where('status', true)->count();
        $usedPickingTrolleys = $warehouse->pickingTrolleys()->where('status', true)->whereNotNull('delivery_note_id')->count();


        $stats = [
            'number_current_picking_trolleys' => $currentPickingTrolleys,
            'number_current_picking_trolleys_in_use' => $usedPickingTrolleys,
            'number_picking_trolleys' => $allPickingTrolleys
        ];



        $warehouse->stats()->update($stats);
    }
}
