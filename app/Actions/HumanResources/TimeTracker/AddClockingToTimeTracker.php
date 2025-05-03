<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 12:33:44 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\TimeTracker;

use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\TimeTracker;
use Lorisleiva\Actions\Concerns\AsObject;

class AddClockingToTimeTracker
{
    use AsObject;


    public function handle(Timesheet $timesheet, Clocking $clocking, int $hydratorsDelay = 0): TimeTracker
    {
        /** @var TimeTracker $timeTracker */
        $timeTracker = $timesheet->timeTrackers()->where('status', TimeTrackerStatusEnum::OPEN)->first();
        if (!$timeTracker) {
            $timeTracker = StoreTimeTracker::make()->action(
                $timesheet,
                $clocking,
                []
            );
        } else {
            CloseTimeTracker::make()->action($timeTracker, $clocking, [], $hydratorsDelay);
        }

        $clocking->update(
            [
                'time_tracker_id' => $timeTracker->id
            ]
        );

        return $timeTracker;
    }
}
