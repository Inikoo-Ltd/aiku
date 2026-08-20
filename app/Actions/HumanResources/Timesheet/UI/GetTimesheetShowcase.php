<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 09:57:32 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Timesheet\UI;

use App\Actions\HumanResources\Timesheet\CalculateTimesheetOvertime;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Timesheet;
use Lorisleiva\Actions\Concerns\AsAction;

class GetTimesheetShowcase
{
    use AsAction;

    public function handle(Timesheet $timesheet): array
    {
        $workStartAt = $timesheet->start_at;
        $workEndAt = $timesheet->end_at;

        $overtime = CalculateTimesheetOvertime::run($timesheet);

        return [
            'id'                        => $timesheet->id,
            'date'                      => $timesheet->date->toDateString(),
            'store_clocking_route'      => route('grp.models.timesheet.clocking.store', $timesheet->id),
            'work_start_at'            => $workStartAt,
            'work_end_at'              => $workEndAt,
            'work_duration'            => $timesheet->working_duration,
            'breaks_duration'          => $timesheet->breaks_duration,
            'total_duration'           => $timesheet->total_duration,
            'paid_duration'            => $overtime['paid_duration'],
            'unpaid_overtime_duration' => $overtime['unpaid_overtime_duration'],
            'paid_overtime_duration'   => $overtime['paid_overtime_duration'],
            'overtime'                 => $overtime['unpaid_overtime_duration'] + $overtime['paid_overtime_duration'],
            'about'                    => $timesheet->about,
            'scheduled_hours'          => $this->getScheduledHours($timesheet),
        ];
    }

    private function getScheduledHours(Timesheet $timesheet): array
    {
        if ($timesheet->subject_type !== 'Employee' || !$timesheet->subject) {
            return ['source' => null, 'start_time' => null, 'end_time' => null, 'breaks' => []];
        }

        /** @var Employee $employee */
        $employee = $timesheet->subject;

        $ownSchedule  = $employee->getDefaultWorkSchedule();
        $workSchedule = $ownSchedule ?? $employee->organisation->getDefaultWorkSchedule();

        if (!$workSchedule) {
            return ['source' => null, 'start_time' => null, 'end_time' => null, 'breaks' => []];
        }

        $day = $workSchedule->days()->where('day_of_week', $timesheet->date->dayOfWeekIso)->first();

        if (!$day) {
            return [
                'source'     => $ownSchedule ? 'employee' : 'organisation',
                'start_time' => null,
                'end_time'   => null,
                'breaks'     => [],
            ];
        }

        return [
            'source'     => $ownSchedule ? 'employee' : 'organisation',
            'start_time' => $day->start_time,
            'end_time'   => $day->end_time,
            'breaks'     => $day->breaks->map(fn ($break) => [
                'name'       => $break->break_name,
                'start_time' => $break->start_time?->format('H:i'),
                'end_time'   => $break->end_time?->format('H:i'),
            ])->values(),
        ];
    }
}
