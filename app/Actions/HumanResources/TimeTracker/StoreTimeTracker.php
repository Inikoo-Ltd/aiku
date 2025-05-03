<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 12:33:44 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\TimeTracker;

use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateTimeTracker;
use App\Actions\HumanResources\Timesheet\Hydrators\TimesheetHydrateTimeTrackers;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Guest\Hydrators\GuestHydrateTimeTracker;
use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\TimeTracker;

class StoreTimeTracker extends OrgAction
{
    public function handle(Timesheet $timesheet, Clocking $clocking, array $modelData): TimeTracker
    {
        data_set($modelData, 'workplace_id', $clocking->workplace_id);
        data_set($modelData, 'status', TimeTrackerStatusEnum::OPEN);
        data_set($modelData, 'starts_at', $clocking->clocked_at);
        data_set($modelData, 'start_clocking_id', $clocking->id);
        data_set($modelData, 'subject_type', $timesheet->subject_type);
        data_set($modelData, 'subject_id', $timesheet->subject_id);

        /** @var TimeTracker $timeTracker */
        $timeTracker = $timesheet->timeTrackers()->create($modelData);

        if ($timeTracker->subject_type === 'Employee') {
            EmployeeHydrateTimeTracker::dispatch($timeTracker->subject);
        } else {
            GuestHydrateTimeTracker::dispatch($timeTracker->subject);
        }

        if ($timesheet->number_time_trackers === 0) {
            $timesheet->update(
                [
                    'start_at'             => $clocking->clocked_at,
                    'number_time_trackers' => 1
                ]
            );
        }

        TimesheetHydrateTimeTrackers::dispatch($timesheet);

        return $timeTracker;
    }


    public function action(Timesheet $timesheet, Clocking $clocking, $modelData): TimeTracker
    {
        $this->asAction = true;
        $this->initialisation($timesheet->organisation, $modelData);

        return $this->handle($timesheet, $clocking, $this->validatedData);
    }


}
