<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Sept 2024 21:29:12 Malaysia Time, Taipei, Taiwan
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateDeliveryNotes;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateDeliveryNotes implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateDeliveryNotes;


    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(int $groupId, DeliveryNoteTypeEnum $type): string
    {
        return $groupId.'-'.$type->value;
    }


    public function handle(int $groupId, DeliveryNoteTypeEnum $type): void
    {
        $group = Group::find($groupId);
        if (!$group) {
            return;
        }

        if ($type == DeliveryNoteTypeEnum::ORDER) {
            $stats = $this->getStoreDeliveryNotesStats($group);
        } else {
            $stats = $this->getStoreReplacementsStats($group);
        }


        $group->orderingStats()->update($stats);
    }


}
