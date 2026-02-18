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

class GroupHydratePickedBays implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Group $group): string
    {
        return (string) $group->id;
    }

    public function handle(Group $group): void
    {
        $allPickedBays = $group->pickedBays()->count();
        $currentPickedBays = $group->pickedBays()->where('status', true)->count();
        $usedPickedBays = $group->pickedBays()->where('status', true)
            ->whereNotNull('current_delivery_note_id')->count();


        $stats = [
            'number_current_picked_bays' => $currentPickedBays,
            'number_current_picked_bays_in_use' => $usedPickedBays,
            'number_picked_bays' => $allPickedBays
        ];



        $group->inventoryStats()->update($stats);
    }
}
