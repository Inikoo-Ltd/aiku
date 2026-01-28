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

class OrganisationHydratePickedBays implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id;
    }


    public function handle(Organisation $organisation): void
    {
        $allPickedBays = $organisation->pickedBays()->count();
        $currentPickedBays = $organisation->pickedBays()->where('status', true)->count();
        $usedPickedBays = $organisation->pickedBays()->where('status', true)->whereNotNull('delivery_note_id')->count();


        $stats = [
            'number_current_picked_bays' => $currentPickedBays,
            'number_current_picked_bays_in_use' => $usedPickedBays,
            'number_picked_bays' => $allPickedBays
        ];



        $organisation->inventoryStats()->update($stats);
    }
}
