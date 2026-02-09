<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:15:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateTrolleys implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id;
    }


    public function handle(Organisation $organisation): void
    {
        $allPickingTrolleys     = $organisation->trolleys()->count();
        $currentPickingTrolleys = $organisation->trolleys()->where('status', true)->count();
        $usedPickingTrolleys    = $organisation->trolleys()->where('status', true)->whereNotNull('current_delivery_note_id')->count();


        $stats = [
            'number_current_picking_trolleys'        => $currentPickingTrolleys,
            'number_current_picking_trolleys_in_use' => $usedPickingTrolleys,
            'number_picking_trolleys'                => $allPickingTrolleys
        ];


        $organisation->inventoryStats()->update($stats);
    }
}
