<?php

/*
 * author Louis Perez
 * created on 15-05-2026-09h-25m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateReturnDeliveryNotes implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(Group $group): string
    {
        return $group->id . '-return_delivery_note_state';
    }

    public function handle(Group $group): void
    {
        $stats = [
            'number_return_delivery_notes'     =>   $group->returnDeliveryNotes()->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model: 'return_delivery_notes',
            field: 'state',
            enum: ReturnDeliveryNoteStateEnum::class,
            models: ReturnDeliveryNote::class,
            where: function ($q) use ($group) {
                $q->whereNull('deleted_at')
                    ->where('group_id', $group->id);
            }
        ));

        $group->procurementStats()->update($stats);
    }
}
