<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:15:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateLocations implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id;
    }

    public function handle(Organisation $organisation): void
    {
        $locations = $organisation->locations()->count();
        $operationalLocations = $organisation->locations()->where('status', LocationStatusEnum::OPERATIONAL)->count();

        $stats = [
            'number_locations' => $locations,
            'number_locations_status_operational' => $operationalLocations,
            'number_locations_status_broken' => $locations - $operationalLocations,
        ];

        $organisation->inventoryStats()->update($stats);
    }
}
