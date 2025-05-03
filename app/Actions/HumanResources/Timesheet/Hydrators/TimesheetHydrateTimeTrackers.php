<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 12:33:44 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Timesheet\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\TimeTracker;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class TimesheetHydrateTimeTrackers implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Timesheet $timesheet): string
    {
        return $timesheet->id;
    }

    public function handle(Timesheet $timesheet): void
    {
        $timesheet->refresh();

        $stats = [
            'number_time_trackers' => $timesheet->timeTrackers()->count(),
        ];

        $timeTrackers = $timesheet->timeTrackers()->orderBy('starts_at')->get();

        $workDuration             = 0;
        $breakDuration            = 0;
        $breakStart               = null;
        $numberClosedTimeTrackers = 0;
        /** @var TimeTracker $timeTracker */
        foreach ($timeTrackers as $timeTracker) {
            if ($breakStart) {
                $breakDuration += $breakStart->diffInSeconds($timeTracker->starts_at);
                $breakStart    = null;
            }

            if ($timeTracker->status == TimeTrackerStatusEnum::CLOSED) {
                $numberClosedTimeTrackers++;
                $workDuration += $timeTracker->duration;
                $breakStart   = $timeTracker->ends_at;
            }
        }

        $stats['number_open_time_trackers'] = $stats['number_time_trackers'] - $numberClosedTimeTrackers;

        if ($timesheet->end_at) {
            $stats['total_duration'] = $timesheet->start_at->diffInSeconds($timesheet->end_at);
        }

        if ($numberClosedTimeTrackers) {
            $stats['working_duration'] = $workDuration;
            $stats['breaks_duration']  = $breakDuration;
        }

        $timesheet->update($stats);
    }


}
