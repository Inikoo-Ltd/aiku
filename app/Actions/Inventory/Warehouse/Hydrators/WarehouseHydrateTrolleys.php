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

class WarehouseHydrateTrolleys implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Warehouse $warehouse): string
    {
        return $warehouse->id;
    }


    public function handle(Warehouse $warehouse): void
    {
        $allTrolleys     = $warehouse->trolleys()->count();
        $currentTrolleys = $warehouse->trolleys()->where('status', true)->count();
        $usedTrolleys    = $warehouse->trolleys()->where('status', true)->whereNotNull('current_delivery_note_id')->count();


        $stats = [
            'number_current_picking_trolleys'        => $currentTrolleys,
            'number_current_picking_trolleys_in_use' => $usedTrolleys,
            'number_picking_trolleys'                => $allTrolleys
        ];


        $warehouse->stats()->update($stats);
    }
}
