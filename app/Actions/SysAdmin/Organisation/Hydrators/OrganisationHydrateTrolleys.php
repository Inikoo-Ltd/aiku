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
        $allTrolleys     = $organisation->trolleys()->count();
        $currentTrolleys = $organisation->trolleys()->where('status', true)->count();
        $usedTrolleys    = $organisation->trolleys()->where('status', true)->whereNotNull('current_delivery_note_id')->count();


        $stats = [
            'number_current_picking_trolleys'        => $currentTrolleys,
            'number_current_picking_trolleys_in_use' => $usedTrolleys,
            'number_picking_trolleys'                => $allTrolleys
        ];


        $organisation->inventoryStats()->update($stats);
    }
}
