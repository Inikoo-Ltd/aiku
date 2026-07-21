<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:46:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\HumanResources;

use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Enums\HumanResources\Leave\LeaveTypeEnum;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\Timesheet;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowHumanResourcesDashboard extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    public function asController(Organisation $organisation, ActionRequest $request): ActionRequest
    {
        $this->initialisation($organisation, $request);

        return $request;
    }


    public function htmlResponse(ActionRequest $request): Response
    {
        $title = __('Human Resources');
        $today = now()->startOfDay();

        $attendance      = $this->getTodayAttendance($today);
        $onLeaveCount    = $this->getOnLeaveTodayCount($today);
        $presentCount    = $attendance->count();
        $lateCount       = $attendance->where('is_late', true)->count();
        $workingCount    = $this->organisation->humanResourcesStats->number_employees_state_working;
        $absentCount     = max(0, $workingCount - $presentCount - $onLeaveCount);

        return Inertia::render(
            'Org/HumanResources/HumanResourcesDashboard',
            [
                'breadcrumbs'   => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'         => $title,
                'pageHead'      => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-user-hard-hat'],
                        'title' => $title
                    ],
                    'iconRight' => [
                        'icon'    => ['fal', 'fa-chart-network'],
                        'tooltip' => __('Org chart'),
                        'url'     => [
                            'name'       => 'grp.org.hr.org_chart',
                            'parameters' => $request->route()->originalParameters(),
                        ],
                    ],
                    'title'     => $title,
                ],
                'stats'         => [
                    [
                        'name'  => __('Employees'),
                        'stat'  => $workingCount,
                        'color' => 'indigo',
                        'icon'  => ['fal', 'fa-users'],
                        'route' => [
                            'name'       => 'grp.org.hr.employees.index',
                            'parameters' => array_merge(
                                [
                                    '_query' => [
                                        'elements[state]' => 'working'
                                    ]
                                ],
                                $request->route()->originalParameters()
                            )
                        ]
                    ],
                    [
                        'name'  => __('Working places'),
                        'stat'  => $this->organisation->humanResourcesStats->number_workplaces,
                        'color' => 'teal',
                        'icon'  => ['fal', 'fa-building'],
                        'route' => [
                            'name'       => 'grp.org.hr.workplaces.index',
                            'parameters' => $request->route()->originalParameters()
                        ]
                    ],
                    [
                        'name'  => __('Responsibilities'),
                        'stat'  => $this->organisation->humanResourcesStats->number_job_positions,
                        'color' => 'purple',
                        'icon'  => ['fal', 'fa-sitemap'],
                        'route' => [
                            'name'       => 'grp.org.hr.job_positions.index',
                            'parameters' => $request->route()->originalParameters()
                        ]
                    ],
                    [
                        'name'  => __('Present today'),
                        'stat'  => $presentCount,
                        'color' => 'green',
                        'icon'  => ['fal', 'fa-user-check'],
                    ],
                    [
                        'name'  => __('On leave today'),
                        'stat'  => $onLeaveCount,
                        'color' => 'blue',
                        'icon'  => ['fal', 'fa-umbrella-beach'],
                    ],
                    [
                        'name'  => __('Late today'),
                        'stat'  => $lateCount,
                        'color' => 'amber',
                        'icon'  => ['fal', 'fa-clock'],
                    ],
                    [
                        'name'  => __('Absent today'),
                        'stat'  => $absentCount,
                        'color' => 'red',
                        'icon'  => ['fal', 'fa-user-slash'],
                    ],
                ],
                'attendance'    => $attendance->values()->all(),
                'birthdays'     => $this->getBirthdaysThisMonth($today),
                'leaveOverview' => $this->getLeaveOverview($today),
                'employeeLeaves' => $this->getEmployeeLeaves($today),
                'leaveTypes'    => $this->getLeaveTypesDistribution($today),
            ]
        );
    }

    private function getLeaveOverview(Carbon $today): array
    {
        $startOfWeek = $today->copy()->startOfWeek(Carbon::MONDAY);

        $days = [];
        for ($i = 0; $i < 5; $i++) {
            $day = $startOfWeek->copy()->addDays($i);

            $days[] = [
                'label'    => $day->format('D'),
                'count'    => $this->countEmployeesOnLeaveForDay($day),
                'is_today' => $day->isSameDay($today),
            ];
        }

        return $days;
    }

    private function countEmployeesOnLeaveForDay(Carbon $day): int
    {
        return Leave::where('organisation_id', $this->organisation->id)
            ->where('status', LeaveStatusEnum::APPROVED)
            ->whereDate('start_date', '<=', $day->toDateString())
            ->whereDate('end_date', '>=', $day->toDateString())
            ->distinct('employee_id')
            ->count('employee_id');
    }

    private function getEmployeeLeaves(Carbon $today): array
    {
        $leaves = Leave::where('organisation_id', $this->organisation->id)
            ->where('status', LeaveStatusEnum::APPROVED)
            ->whereDate('end_date', '>=', $today->toDateString())
            ->with(['employee' => fn ($query) => $query->with('image'), 'leaveType'])
            ->orderBy('start_date')
            ->limit(20)
            ->get();

        return $leaves->map(function (Leave $leave): array {
            return [
                'id'          => $leave->id,
                'name'        => $leave->employee?->contact_name ?: $leave->employee_name,
                'avatar'      => $leave->employee
                    ? $this->getAvatar($leave->employee)
                    : 'https://api.dicebear.com/7.x/avataaars/svg?seed='.rawurlencode((string)$leave->employee_name),
                'type_name'   => $this->resolveLeaveTypeName($leave),
                'type_color'  => $this->leaveColorHex($leave->leaveType?->color),
                'date_label'  => $this->formatLeaveRange($leave),
            ];
        })->all();
    }

    private function getLeaveTypesDistribution(Carbon $today): array
    {
        $startMonth = $today->copy()->startOfMonth()->toDateString();
        $endMonth   = $today->copy()->endOfMonth()->toDateString();

        $leaves = Leave::where('organisation_id', $this->organisation->id)
            ->where('status', LeaveStatusEnum::APPROVED)
            ->whereDate('start_date', '<=', $endMonth)
            ->whereDate('end_date', '>=', $startMonth)
            ->with('leaveType:id,name,color')
            ->get(['id', 'employee_id', 'leave_type_id', 'type']);

        $groups = [];
        foreach ($leaves as $leave) {
            $name = $this->resolveLeaveTypeName($leave);

            if (!isset($groups[$name])) {
                $groups[$name] = [
                    'name'      => $name,
                    'color'     => $leave->leaveType?->color,
                    'employees' => [],
                ];
            }

            $groups[$name]['color']                     ??= $leave->leaveType?->color;
            $groups[$name]['employees'][$leave->employee_id] = true;
        }

        $counts = array_map(fn (array $group): int => count($group['employees']), $groups);
        $total  = (int)array_sum($counts);

        $palette = ['#6366f1', '#22c55e', '#3b82f6', '#f59e0b', '#ef4444', '#a855f7', '#14b8a6', '#ec4899', '#f97316', '#06b6d4'];

        $types = collect($groups)
            ->values()
            ->map(function (array $group, int $index) use ($total, $palette): array {
                $count = count($group['employees']);

                return [
                    'name'       => $group['name'],
                    'color'      => $group['color'] ? $this->leaveColorHex($group['color']) : $palette[$index % count($palette)],
                    'count'      => $count,
                    'percentage' => $total > 0 ? round($count / $total * 100, 1) : 0,
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();

        return [
            'total' => $total,
            'types' => $types,
        ];
    }

    private function resolveLeaveTypeName(Leave $leave): string
    {
        if ($leave->leaveType?->name) {
            return $leave->leaveType->name;
        }

        $type = is_string($leave->type) ? $leave->type : null;

        if ($type) {
            return LeaveTypeEnum::labels()[$type] ?? Str::headline($type);
        }

        return __('Leave');
    }

    private function formatLeaveRange(Leave $leave): string
    {
        $start = $leave->start_date;
        $end   = $leave->end_date;

        if (!$start || !$end) {
            return '';
        }

        if ($start->isSameDay($end)) {
            return $start->format('j M Y');
        }

        if ($start->format('m Y') === $end->format('m Y')) {
            return $start->format('j').'–'.$end->format('j M Y');
        }

        return $start->format('j M').' – '.$end->format('j M Y');
    }

    private function leaveColorHex(?string $color): string
    {
        $map = [
            'indigo' => '#6366f1',
            'green'  => '#22c55e',
            'blue'   => '#3b82f6',
            'gray'   => '#6b7280',
            'grey'   => '#6b7280',
            'red'    => '#ef4444',
            'purple' => '#a855f7',
            'amber'  => '#f59e0b',
            'yellow' => '#eab308',
            'orange' => '#f97316',
            'teal'   => '#14b8a6',
            'cyan'   => '#06b6d4',
            'pink'   => '#ec4899',
            'lime'   => '#84cc16',
            'emerald' => '#10b981',
        ];

        if (!$color) {
            return '#94a3b8';
        }

        if (str_starts_with($color, '#')) {
            return $color;
        }

        return $map[strtolower($color)] ?? '#94a3b8';
    }

    private function getTodayAttendance(Carbon $today): Collection
    {
        $timesheets = Timesheet::where('timesheets.organisation_id', $this->organisation->id)
            ->where('subject_type', 'Employee')
            ->whereDate('date', $today->toDateString())
            ->with(['subject' => function ($query) {
                $query->select(['id', 'contact_name', 'alias', 'job_title', 'slug', 'image_id'])->with('image');
            }])
            ->addSelect(['first_is_late' => Clocking::select('is_late')
                ->whereColumn('clockings.timesheet_id', 'timesheets.id')
                ->orderBy('clocked_at')
                ->limit(1)])
            ->addSelect(['first_clocking_notes' => Clocking::select('notes')
                ->whereColumn('clockings.timesheet_id', 'timesheets.id')
                ->orderBy('clocked_at')
                ->orderBy('id')
                ->limit(1)])
            ->orderBy('start_at')
            ->get();

        return $timesheets
            ->filter(fn (Timesheet $timesheet): bool => $timesheet->subject !== null)
            ->map(function (Timesheet $timesheet): array {
                $employee     = $timesheet->subject;
                $clockInCount = (int)$timesheet->number_time_trackers;

                return [
                    'id'               => $timesheet->id,
                    'employee_name'    => $employee->contact_name ?: ($employee->alias ?: $employee->slug),
                    'job_title'        => $employee->job_title,
                    'avatar'           => $this->getAvatar($employee),
                    'start_at'         => $timesheet->start_at,
                    'end_at'           => $timesheet->end_at,
                    'notes'            => $timesheet->first_clocking_notes,
                    'is_open'          => $timesheet->number_open_time_trackers > 0,
                    'is_late'          => (bool)$timesheet->first_is_late,
                    'working_duration' => (int)$timesheet->working_duration,
                    'breaks_duration'  => (int)$timesheet->breaks_duration,
                    'clock_in_count'   => $clockInCount,
                    'clock_out_count'  => $clockInCount - (int)$timesheet->number_open_time_trackers,
                    'route'            => [
                        'name'       => 'grp.org.hr.timesheets.show',
                        'parameters' => [
                            'organisation' => $this->organisation->slug,
                            'timesheet'    => $timesheet->id,
                        ],
                    ],
                ];
            });
    }

    private function getOnLeaveTodayCount(Carbon $today): int
    {
        return Leave::where('organisation_id', $this->organisation->id)
            ->where('status', LeaveStatusEnum::APPROVED)
            ->whereDate('start_date', '<=', $today->toDateString())
            ->whereDate('end_date', '>=', $today->toDateString())
            ->distinct('employee_id')
            ->count('employee_id');
    }

    private function getBirthdaysThisMonth(Carbon $today): array
    {
        $employees = $this->organisation->employees()
            ->where('state', '!=', EmployeeStateEnum::LEFT->value)
            ->whereNotNull('date_of_birth')
            ->whereRaw('EXTRACT(MONTH FROM date_of_birth) = ?', [$today->month])
            ->orderByRaw('EXTRACT(DAY FROM date_of_birth)')
            ->with('image')
            ->get();

        return $employees->map(function (Employee $employee) use ($today): array {
            $birthday = $employee->date_of_birth;

            return [
                'id'         => $employee->id,
                'name'       => $employee->contact_name ?: ($employee->alias ?: $employee->slug),
                'job_title'  => $employee->job_title,
                'avatar'     => $this->getAvatar($employee),
                'day'        => (int)$birthday->format('d'),
                'date_label' => $birthday->format('F j'),
                'is_today'   => $birthday->format('m-d') === $today->format('m-d'),
            ];
        })->values()->all();
    }

    private function getAvatar(Employee $employee): string
    {
        return Arr::get(
            $employee->imageSources(120, 120),
            'original',
            'https://api.dicebear.com/7.x/avataaars/svg?seed='.rawurlencode((string)$employee->slug)
        );
    }


    public function getBreadcrumbs($routeParameters): array
    {
        return
            array_merge(
                ShowOrganisationDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.hr.dashboard',
                                'parameters' => Arr::only($routeParameters, 'organisation')
                            ],
                            'label' => __('Human resources'),
                        ]
                    ]
                ]
            );
    }
}
