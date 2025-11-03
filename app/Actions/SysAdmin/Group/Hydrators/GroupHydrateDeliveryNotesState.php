<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Nov 2025 14:53:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateDeliveryNotes;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateDeliveryNotesState implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateDeliveryNotes;

    public function getJobUniqueId(int $groupId, DeliveryNoteStateEnum $state): string
    {
        return $groupId.'-'.$state->value;
    }

    public function handle(int $groupID, DeliveryNoteStateEnum $state): void
    {
        $group = Group::find($groupID);
        if (!$group) {
            return;
        }

        $stats = $this->getDeliveryStateNotesStats($state, $group);
        if ($state == DeliveryNoteStateEnum::DISPATCHED) {
            $stats2 = $this->getDispatchedDeliveryNotesStats($group);
            $stats2 = array_merge($stats2,$this->getDispatchedReplacementsStats($group));
            $group->orderingStats()->update($stats2);
        }

        $group->orderHandlingStats()->update($stats);
    }


}
