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
use App\Actions\Traits\WithActionUpdate;
use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\TimeTracker;

class CloseTimeTracker extends OrgAction
{
    use WithActionUpdate;

    public function handle(TimeTracker $timeTracker, Clocking $clocking, array $modelData): TimeTracker
    {
        data_set($modelData, 'status', TimeTrackerStatusEnum::CLOSED);
        data_set($modelData, 'ends_at', $clocking->clocked_at);
        data_set($modelData, 'end_clocking_id', $clocking->id);


        $timeTracker = $this->update($timeTracker, $modelData);
        $timeTracker->refresh();
        $timeTracker->timesheet->update(['end_at' => $clocking->clocked_at]);


        $timeTracker->update(
            [
                'duration' => $timeTracker->starts_at->diffInSeconds($timeTracker->ends_at)
            ]
        );


        if ($timeTracker->subject_type === 'Employee') {
            EmployeeHydrateTimeTracker::dispatch($timeTracker->subject)->delay($this->hydratorsDelay);
        } else {
            GuestHydrateTimeTracker::dispatch($timeTracker->subject)->delay($this->hydratorsDelay);
        }

        TimesheetHydrateTimeTrackers::dispatch($timeTracker->timesheet)->delay($this->hydratorsDelay);
        return $timeTracker;
    }



    public function action(TimeTracker $timeTracker, Clocking $clocking, $modelData, int $hydratorsDelay = 0): TimeTracker
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($timeTracker->timesheet->organisation, $modelData);

        return $this->handle($timeTracker, $clocking, $this->validatedData);
    }


}
