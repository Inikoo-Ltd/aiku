<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 12:33:44 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\TimeTracker;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class EmployeeHydrateTimeTracker implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Employee $employee): string
    {
        return $employee->id;
    }

    public function handle(Employee $employee): void
    {
        $stats = [
            'number_time_trackers' => $employee->timeTrackers()->count(),
        ];


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'time_trackers',
                field: 'status',
                enum: TimeTrackerStatusEnum::class,
                models: TimeTracker::class,
                where: function ($q) use ($employee) {
                    $q->where('subject_type', 'Employee')->where('subject_id', $employee->id);
                }
            )
        );


        $employee->stats()->update($stats);
    }


}
