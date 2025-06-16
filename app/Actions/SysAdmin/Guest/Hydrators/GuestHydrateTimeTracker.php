<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 12:33:44 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Guest\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
use App\Models\HumanResources\TimeTracker;
use App\Models\SysAdmin\Guest;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GuestHydrateTimeTracker implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Guest $guest): string
    {
        return $guest->id;
    }

    public function handle(Guest $guest): void
    {
        $stats = [
            'number_time_trackers' => $guest->timeTrackers()->count(),
        ];


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'time_trackers',
                field: 'status',
                enum: TimeTrackerStatusEnum::class,
                models: TimeTracker::class,
                where: function ($q) use ($guest) {
                    $q->where('subject_type', 'Guest')->where('subject_id', $guest->id);
                }
            )
        );


        $guest->stats()->update($stats);
    }


}
