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
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateLocations
{
    use AsAction;
    use WithEnumStats;

    private Group $group;
    public string $jobQueue = 'low-priority';

    public function __construct(Group $group)
    {
        $this->group = $group;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->group->id))->dontRelease()];
    }


    public function handle(Group $group): void
    {
        $locations            = $group->locations()->count();
        $operationalLocations = $group->locations()->where('status', LocationStatusEnum::OPERATIONAL)->count();


        $stats = [
            'number_locations'                    => $locations,
            'number_locations_status_operational' => $operationalLocations,
            'number_locations_status_broken'      => $locations - $operationalLocations
        ];



        $group->inventoryStats()->update($stats);
    }
}
