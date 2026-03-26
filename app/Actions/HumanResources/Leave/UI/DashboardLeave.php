<?php

namespace App\Actions\HumanResources\Leave\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Leave;
use App\Models\SysAdmin\Organisation;
use App\Services\HumanResources\LeaveTypeResolver;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;

class DashboardLeave extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithLeaveSubNavigation;

    /**
     * @param Organisation $organisation
     * @param ActionRequest $request
     *
     * @return Response
     */
    public function handle(Organisation $organisation, ActionRequest $request): Response
    {
        $year = (int)$request->input('year', now()->year);
        $month = (int)$request->input('month', now()->month);
        $employeeId = $request->input('employee_id');
        $type = $request->input('type');
        $department = $request->input('department');
        $search = $request->input('search');
        $sortBy = $request->input('sort_by', 'name');
        $view = $request->input('view') === 'week' ? 'week' : 'month';
        $weekStartInput = $request->input('week_start');

        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

        if ($view === 'week') {
            $weekStart = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY);

            if ($weekStartInput) {
                try {
                    $weekStart = Carbon::parse($weekStartInput)->startOfWeek(Carbon::MONDAY)->startOfDay();
                } catch (\Throwable) {
                    $weekStart = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY);
                }
            }

            $visibleStart = $weekStart;
            $visibleEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        } else {
            $visibleStart = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $visibleEnd = $endOfMonth->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
            $weekStart = null;
        }

        $employeesQuery = $organisation->employees()
            ->where('state', 'working');

        if ($employeeId) {
            $employeesQuery->where('id', $employeeId);
        }

        if ($department) {
            $employeesQuery->whereHas('jobPositions', function ($query) use ($department) {
                $query->where('department', $department);
            });
        }

        if ($search) {
            $employeesQuery->where(function ($query) use ($search) {
                $query->whereWith('contact_name', $search)
                    ->orWhereWith('work_email', $search)
                    ->orWhereHas('jobPositions', function ($query) use ($search) {
                        $query->whereWith('department', $search)
                            ->orWhereWith('name', $search);
                    });
            });
        }

        // Apply sorting
        switch ($sortBy) {
            case 'last_name':
                $employeesQuery
                    ->orderByRaw("(string_to_array((trim(coalesce(contact_name, '')) COLLATE \"C\"), ' '))[array_length(string_to_array((trim(coalesce(contact_name, '')) COLLATE \"C\"), ' '), 1)]")
                    ->orderBy('contact_name');
                break;
            case 'first_name':
                $employeesQuery
                    ->orderByRaw("split_part((trim(coalesce(contact_name, '')) COLLATE \"C\"), ' ', 1)")
                    ->orderBy('contact_name');
                break;
            case 'department':
                $employeesQuery->select('employees.*')
                    ->leftJoin('employee_has_job_positions', 'employees.id', '=', 'employee_has_job_positions.employee_id')
                    ->leftJoin('job_positions', 'employee_has_job_positions.job_position_id', '=', 'job_positions.id')
                    ->orderBy('job_positions.department')
                    ->orderBy('contact_name');
                break;
            default:
                $employeesQuery->orderBy('contact_name');
        }

        $employees = $employeesQuery->get();

        $leavesQuery = Leave::query()
            ->where('organisation_id', $organisation->id)
            ->whereIn('status', [
                LeaveStatusEnum::APPROVED->value,
                LeaveStatusEnum::PENDING->value,
            ])
            ->whereDate('start_date', '<=', $visibleEnd->toDateString())
            ->whereDate('end_date', '>=', $visibleStart->toDateString())
            ->with(['employee', 'leaveType']);

        if ($type) {
            $leavesQuery->where('type', $type);
        }

        if ($employeeId) {
            $leavesQuery->where('employee_id', $employeeId);
        }

        $leaves = $leavesQuery->get()->groupBy('employee_id');

        // Get public holidays for the visible date range
        $holidays = $organisation->holidays()
            ->forDateRange($visibleStart, $visibleEnd)
            ->get();

        $calendarData = $employees->map(function (Employee $employee) use ($leaves, $visibleStart, $visibleEnd) {
            $employeeLeaves = $leaves->get($employee->id, collect());

            $visibleLeaves = $employeeLeaves->filter(function ($leave) use ($visibleStart, $visibleEnd) {
                $leaveStart = Carbon::parse($leave->start_date);
                $leaveEnd = Carbon::parse($leave->end_date);

                return $leaveStart->lte($visibleEnd) && $leaveEnd->gte($visibleStart);
            });

            // Get current job position for department info
            $currentJobPosition = $employee->jobPositions()->first();
            $department = $currentJobPosition?->department;
            $jobTitle = $currentJobPosition?->name;

            return [
                'id' => $employee->id,
                'name' => $employee->contact_name,
                'job_title' => $jobTitle,
                'department' => $department,
                'leaves' => $visibleLeaves->map(function ($leave) {
                    return [
                        'id' => $leave->id,
                        'employee_name' => $leave->employee_name,
                        'start_date' => $leave->start_date?->format('Y-m-d'),
                        'end_date' => $leave->end_date?->format('Y-m-d'),
                        'type' => $leave->type,
                        'type_label' => $leave->leaveType?->name ?? $leave->type,
                        'code' => $leave->leaveType?->code ?? $leave->type,
                        'color' => $leave->leaveType?->color ?? 'gray',
                        'duration_days' => $leave->duration_days,
                        'reason' => $leave->reason,
                        'status' => $leave->status?->value,
                    ];
                }),
            ];
        });

        $weeks = $this->buildWeeks(
            $visibleStart,
            $visibleEnd,
            $view === 'month' ? $startOfMonth : null,
            $view === 'month' ? $endOfMonth : null,
        );

        $employeeOptions = $organisation->employees()
            ->where('state', 'working')
            ->orderBy('alias')
            ->get()
            ->map(fn($employee) => [
                'value' => $employee->id,
                'label' => $employee->contact_name,
            ]);

        $departmentOptions = $organisation->jobPositions()
            ->whereNotNull('department')
            ->distinct('department')
            ->orderBy('department')
            ->pluck('department')
            ->map(fn($dept) => [
                'value' => $dept,
                'label' => $dept,
            ]);

        return Inertia::render('Org/HumanResources/DashboardLeave', [
            'breadcrumbs' => $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'title' => __('Leave Dashboard'),
            'pageHead' => [
                'icon' => [
                    'icon' => ['fal', 'fa-user-hard-hat'],
                    'title' => __('Human resources')
                ],
                'iconRight' => [
                    'icon' => ['fal', 'fa-calendar-minus'],
                    'title' => __('Leave')
                ],
                'title' => __('Leave Dashboard'),
                'subNavigation' => $this->getLeaveSubNavigation($request),
            ],
            'filters' => [
                'year' => $year,
                'month' => $month,
                'employee_id' => $employeeId ? (int)$employeeId : null,
                'type' => $type,
                'department' => $department,
                'search' => $search,
                'sort_by' => $sortBy,
                'view' => $view,
                'week_start' => $weekStart?->toDateString(),
            ],
            'calendarData' => $calendarData,
            'weeks' => $weeks,
            'visibleRange' => [
                'start' => $visibleStart->toDateString(),
                'end' => $visibleEnd->toDateString(),
            ],
            'daysInMonth' => $startOfMonth->daysInMonth,
            'monthName' => $startOfMonth->format('F'),
            'employeeOptions' => $employeeOptions,
            'typeOptions' => collect(LeaveTypeResolver::optionsForOrganisation($organisation->id, false))
                ->map(function (array $data, string $value) {
                    return [
                        'value' => $value,
                        'label' => $data['label'],
                    ];
                })
                ->values(),
            'type_options' => LeaveTypeResolver::optionsForOrganisation($organisation->id, false),
            'status_options' => LeaveStatusEnum::labels(),
            'departmentOptions' => $departmentOptions,
            'holidays' => $holidays->map(function ($holiday) {
                return [
                    'id' => $holiday->id,
                    'label' => $holiday->label,
                    'from' => $holiday->from->format('Y-m-d'),
                    'to' => $holiday->to->format('Y-m-d'),
                    'type' => $holiday->type->value,
                ];
            }),
        ]);
    }

    /**
     * @param Carbon $visibleStart
     * @param Carbon $visibleEnd
     * @param Carbon|null $monthStart
     * @param Carbon|null $monthEnd
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildWeeks(
        Carbon  $visibleStart,
        Carbon  $visibleEnd,
        ?Carbon $monthStart = null,
        ?Carbon $monthEnd = null,
    ): array
    {
        $weeks = [];
        $weekIndex = 0;

        $currentWeekStart = $visibleStart->copy()->startOfWeek(Carbon::MONDAY);
        $lastWeekStart = $visibleEnd->copy()->startOfWeek(Carbon::MONDAY);

        while ($currentWeekStart->lte($lastWeekStart)) {
            $days = [];

            for ($i = 0; $i < 7; $i++) {
                $date = $currentWeekStart->copy()->addDays($i);

                $isCurrentMonth = true;
                if ($monthStart && $monthEnd) {
                    $isCurrentMonth = $date->betweenIncluded($monthStart->copy()->startOfDay(), $monthEnd->copy()->endOfDay());
                }

                $days[] = [
                    'date' => $date->toDateString(),
                    'day_of_month' => $date->day,
                    'is_current_month' => $isCurrentMonth,
                    'is_weekend' => $date->isWeekend(),
                    'week_index' => $weekIndex,
                ];
            }

            $weeks[] = [
                'week_index' => $weekIndex,
                'start' => $currentWeekStart->toDateString(),
                'end' => $currentWeekStart->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
                'days' => $days,
            ];

            $currentWeekStart->addWeek();
            $weekIndex++;
        }

        return $weeks;
    }

    /**
     * @param string $routeName
     * @param array $routeParameters
     *
     * @return array
     */
    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (string $routeName, array $routeParameters) {
            return [
                [
                    'type' => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => $routeName,
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Dashboard'),
                        'icon' => 'fal fa-bars',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.leaves.dashboard' =>
            array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb($routeName, $routeParameters)
            ),
            default => [],
        };
    }
}
