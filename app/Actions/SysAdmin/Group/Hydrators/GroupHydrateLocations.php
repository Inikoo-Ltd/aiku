<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 12:01:31 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateLocations implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }

    public function handle(Group $group): void
    {
        $locations = $group->locations()->count();
        $operationalLocations = $group->locations()->where('status', LocationStatusEnum::OPERATIONAL)->count();

        $stats = [
            'number_locations' => $locations,
            'number_locations_status_operational' => $operationalLocations,
            'number_locations_status_broken' => $locations - $operationalLocations,
        ];

        $group->inventoryStats()->update($stats);
    }
}
