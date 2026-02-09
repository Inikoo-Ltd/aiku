<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Sept 2025 20:21:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateTrolleys implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Group $group): string
    {
        return (string)$group->id;
    }

    public function handle(Group $group): void
    {
        $allPickingTrolleys     = $group->trolleys()->count();
        $currentPickingTrolleys = $group->trolleys()->where('status', true)->count();
        $usedPickingTrolleys    = $group->trolleys()->where('status', true)
            ->whereNotNull('current_delivery_note_id')->count();


        $stats = [
            'number_current_picking_trolleys'        => $currentPickingTrolleys,
            'number_current_picking_trolleys_in_use' => $usedPickingTrolleys,
            'number_picking_trolleys'                => $allPickingTrolleys
        ];


        $group->inventoryStats()->update($stats);
    }
}
