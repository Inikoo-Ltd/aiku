<?php

/*
 * author Arya Permana - Kirin
 * created on 03-01-2025-10h-39m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\HumanResources\Employee\UI;

use App\Actions\SysAdmin\User\GetUserGroupScopeJobPositionsData;
use App\Actions\SysAdmin\User\GetUserOrganisationScopeJobPositionsData;
use App\Actions\Traits\UI\WithPermissionsPictogram;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Http\Resources\HumanResources\EmployeeResource;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\Timesheet;
use Lorisleiva\Actions\Concerns\AsObject;

class GetEmployeeShowcase
{
    use AsObject;
    use WithPermissionsPictogram;

    public function handle(Employee $employee): array
    {
        $user = $employee->getUser();

        if ($user) {
            $jobPositionsOrganisationsData = [];
            foreach ($employee->group->organisations as $organisation) {
                $jobPositionsOrganisationData                       = GetUserOrganisationScopeJobPositionsData::run($user, $organisation);
                $jobPositionsOrganisationsData[$organisation->slug] = $jobPositionsOrganisationData;
            }

            $permissionsGroupData = GetUserGroupScopeJobPositionsData::run($user);
            $pictogram            = $this->getPermissionsPictogram($user, $permissionsGroupData, $jobPositionsOrganisationsData);
        } else {
            $pictogram = null;
        }

        return [
            'employee'              => EmployeeResource::make($employee),
            'pin'                   => $employee->pin ? preg_replace('/^\d+:/', '', $employee->pin) : null,
            'regenerate_pin_route'  => route('grp.org.hr.employees.regenerate-pin', [$employee->organisation->slug, $employee->slug]),
            'permissions_pictogram' => $pictogram,
            'work_schedule'         => $this->getWorkScheduleData($employee),
            'attendance'            => $this->getAttendanceData($employee),
            'leave'                 => $this->getLeaveData($employee),
        ];
    }

    private function getLeaveData(Employee $employee): array
    {
        $balance = $employee->currentLeaveBalance;

        return [
            'balance' => $balance ? [
                'annual_used'      => (float)$balance->annual_used,
                'annual_remaining' => $balance->annual_remaining,
                'medical_used'     => (float)$balance->medical_used,
                'unpaid_used'      => (float)$balance->unpaid_used,
                'period_end'       => $balance->period_end?->toDateString(),
            ] : null,
            'recent'  => Leave::where('employee_id', $employee->id)
                ->with('leaveType')
                ->orderByDesc('start_date')
                ->limit(5)
                ->get()
                ->map(fn (Leave $leave) => [
                    'id'         => $leave->id,
                    'type'       => $leave->leaveType?->name ?? $leave->type,
                    'start_date' => $leave->start_date->toDateString(),
                    'end_date'   => $leave->end_date->toDateString(),
                    'days'       => $leave->is_half_day ? 0.5 : (int)$leave->duration_days,
                    'status'     => $leave->status->value,
                ])->values(),
            'route'   => [
                'name'       => 'grp.org.hr.leaves.index',
                'parameters' => ['organisation' => $employee->organisation->slug, 'filter' => ['global' => $employee->contact_name]],
            ],
        ];
    }

    private function getAttendanceData(Employee $employee): array
    {
        $since = now()->subDays(30)->startOfDay();

        $timesheets = Timesheet::where('subject_type', 'Employee')
            ->where('subject_id', $employee->id)
            ->where('date', '>=', $since)
            ->addSelect('timesheets.*')
            ->addSelect(['first_is_late' => Clocking::select('is_late')
                ->whereColumn('clockings.timesheet_id', 'timesheets.id')
                ->orderBy('clocked_at')
                ->limit(1)])
            ->orderByDesc('date')
            ->get();

        $totalSeconds = (int)$timesheets->sum('total_duration');
        $daysPresent  = $timesheets->count();

        $leaveDaysThisYear = (float)Leave::where('employee_id', $employee->id)
            ->where('status', LeaveStatusEnum::APPROVED)
            ->whereYear('start_date', now()->year)
            ->sum('duration_days');

        return [
            'days_present'         => $daysPresent,
            'late_days'            => $timesheets->where('first_is_late', true)->count(),
            'total_seconds'        => $totalSeconds,
            'average_seconds'      => $daysPresent ? intdiv($totalSeconds, $daysPresent) : 0,
            'leave_days_this_year' => $leaveDaysThisYear,
            'last_clocking_at'     => $employee->stats?->last_clocking_at,
            'recent'               => $timesheets->take(7)->map(fn (Timesheet $timesheet) => [
                'id'             => $timesheet->id,
                'date'           => $timesheet->date->toDateString(),
                'start_at'       => $timesheet->start_at,
                'end_at'         => $timesheet->end_at,
                'total_duration' => (int)$timesheet->total_duration,
                'is_late'        => (bool)$timesheet->first_is_late,
                'is_open'        => $timesheet->number_open_time_trackers > 0,
                'route'          => [
                    'name'       => 'grp.org.hr.employees.show.timesheets.show',
                    'parameters' => [
                        'organisation' => $employee->organisation->slug,
                        'employee'     => $employee->slug,
                        'timesheet'    => $timesheet->id,
                    ],
                ],
            ])->values(),
        ];
    }

    private function getWorkScheduleData(Employee $employee): array
    {
        $ownSchedule  = $employee->getDefaultWorkSchedule();
        $workSchedule = $ownSchedule ?? $employee->organisation->getDefaultWorkSchedule();

        if (!$workSchedule) {
            return [
                'source' => null,
                'days'   => [],
            ];
        }

        $workSchedule->load('days.breaks');

        $days = $workSchedule->days->sortBy('day_of_week')->map(fn ($day) => [
            'day_of_week' => $day->day_of_week,
            'start_time'  => $day->start_time,
            'end_time'    => $day->end_time,
            'breaks'      => $day->breaks->map(fn ($break) => [
                'name'       => $break->break_name,
                'start_time' => $break->start_time?->format('H:i'),
                'end_time'   => $break->end_time?->format('H:i'),
            ])->values(),
        ])->values();

        return [
            'source' => $ownSchedule ? 'employee' : 'organisation',
            'days'   => $days,
        ];
    }
}
