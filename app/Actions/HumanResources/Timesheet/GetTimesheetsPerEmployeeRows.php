<?php

namespace App\Actions\HumanResources\Timesheet;

use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\UI\HumanResources\TimesheetEmployeeViewEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Timesheet;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetTimesheetsPerEmployeeRows
{
    use AsAction;

    /**
     * Non-paginated equivalent of IndexTimesheets' "By employee" summary, built for
     * exports: every metric is computed regardless of which view was asked for, so
     * callers can freely pick whichever columns they need off each row.
     *
     * @param  array<int, int>|null  $employeeIds  Restrict to these employee ids; null/empty means everyone.
     * @return Collection<int, array>
     */
    public function handle(
        Group|Organisation|Employee|Guest $parent,
        ?array $employeeIds,
        string $from,
        string $to,
        TimesheetEmployeeViewEnum $view
    ): Collection {
        if ($parent instanceof Guest) {
            return collect();
        }

        $employeesQuery = Employee::query();

        if ($parent instanceof Organisation) {
            $employeesQuery->where('organisation_id', $parent->id)->where('state', EmployeeStateEnum::WORKING);
        } elseif ($parent instanceof Employee) {
            $employeesQuery->where('id', $parent->id);
        } else {
            $employeesQuery->where('group_id', $parent->id)->where('state', EmployeeStateEnum::WORKING);
        }

        if (!empty($employeeIds)) {
            $employeesQuery->whereIn('id', $employeeIds);
        }

        $employees = $employeesQuery->orderBy('contact_name')->get(['id', 'contact_name', 'job_title']);

        if ($employees->isEmpty()) {
            return collect();
        }

        $timesheets = Timesheet::query()
            ->where('subject_type', 'Employee')
            ->whereIn('subject_id', $employees->pluck('id'))
            ->whereBetween('date', [$from, $to])
            ->with('timeTrackers')
            ->get();

        $overtimeByTimesheetId = CalculateTimesheetOvertime::make()->handleMany($timesheets);
        $sourceColumn = $view->sourceColumn();

        $totalsByEmployee = [];

        foreach ($timesheets as $timesheet) {
            $employeeId = $timesheet->subject_id;

            if (!isset($totalsByEmployee[$employeeId])) {
                $totalsByEmployee[$employeeId] = $this->emptyTotals();
            }

            $totalsByEmployee[$employeeId]['timesheet_count']++;
            $totalsByEmployee[$employeeId]['clockings']         += $timesheet->number_time_trackers;
            $totalsByEmployee[$employeeId]['working_duration']  += $timesheet->working_duration;
            $totalsByEmployee[$employeeId]['breaks_duration']   += $timesheet->breaks_duration;

            $overtime = $overtimeByTimesheetId->get($timesheet->id) ?? [
                'paid_duration'            => 0,
                'unpaid_overtime_duration' => 0,
                'paid_overtime_duration'   => 0,
            ];

            $totalsByEmployee[$employeeId]['paid_duration']            += $overtime['paid_duration'];
            $totalsByEmployee[$employeeId]['unpaid_overtime_duration'] += $overtime['unpaid_overtime_duration'];
            $totalsByEmployee[$employeeId]['paid_overtime_duration']   += $overtime['paid_overtime_duration'];

            if ($sourceColumn) {
                $metricValue = $sourceColumn === 'working_duration'
                    ? $timesheet->working_duration
                    : ($overtime[$sourceColumn] ?? 0);

                $isoDayOfWeek = $timesheet->date->dayOfWeekIso;
                $dayKey       = $this->weekdayKeyFor($isoDayOfWeek);

                $totalsByEmployee[$employeeId]['by_day'][$dayKey] += $metricValue;
                $totalsByEmployee[$employeeId]['by_day'][$isoDayOfWeek <= 5 ? 'work_week' : 'weekend'] += $metricValue;
            }
        }

        return $employees->map(function (Employee $employee) use ($totalsByEmployee) {
            $totals = $totalsByEmployee[$employee->id] ?? $this->emptyTotals();

            return array_merge([
                'subject_name' => $employee->contact_name,
                'job_position' => $employee->job_title ?: '-',
                'worked'       => $totals['working_duration'],
            ], $totals);
        });
    }

    private function emptyTotals(): array
    {
        return [
            'timesheet_count'          => 0,
            'clockings'                => 0,
            'working_duration'         => 0,
            'breaks_duration'          => 0,
            'paid_duration'            => 0,
            'unpaid_overtime_duration' => 0,
            'paid_overtime_duration'   => 0,
            'by_day'                   => $this->emptyWeekdayBucket(),
        ];
    }

    private function weekdayKeyFor(int $isoDayOfWeek): string
    {
        return [
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
            7 => 'sunday',
        ][$isoDayOfWeek];
    }

    private function emptyWeekdayBucket(): array
    {
        return [
            'monday'    => 0,
            'tuesday'   => 0,
            'wednesday' => 0,
            'thursday'  => 0,
            'friday'    => 0,
            'saturday'  => 0,
            'sunday'    => 0,
            'work_week' => 0,
            'weekend'   => 0,
        ];
    }
}
