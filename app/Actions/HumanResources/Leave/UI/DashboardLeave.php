<?php

namespace App\Actions\HumanResources\Leave\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Enums\HumanResources\Leave\LeaveTypeEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Leave;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;

class DashboardLeave extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithLeaveSubNavigation;

    public function handle(Organisation $organisation, ActionRequest $request): Response
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);
        $employeeId = $request->input('employee_id');
        $type = $request->input('type');

        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

        $employeesQuery = $organisation->employees()
            ->where('state', 'working')
            ->orderBy('contact_name');

        if ($employeeId) {
            $employeesQuery->where('id', $employeeId);
        }

        $employees = $employeesQuery->get();

        $leavesQuery = Leave::query()
            ->where('organisation_id', $organisation->id)
            ->where('status', 'approved')
            ->whereBetween('start_date', [$startOfMonth, $endOfMonth])
            ->with(['employee']);

        if ($type) {
            $leavesQuery->where('type', $type);
        }

        if ($employeeId) {
            $leavesQuery->where('employee_id', $employeeId);
        }

        $leaves = $leavesQuery->get()->groupBy('employee_id');

        $calendarData = $employees->map(function (Employee $employee) use ($leaves, $startOfMonth, $endOfMonth) {
            $employeeLeaves = $leaves->get($employee->id, collect());

            $monthLeaves = $employeeLeaves->filter(function ($leave) use ($startOfMonth, $endOfMonth) {
                $leaveStart = Carbon::parse($leave->start_date);
                $leaveEnd = Carbon::parse($leave->end_date);
                $monthStart = $startOfMonth->copy()->startOfMonth();
                $monthEnd = $endOfMonth->copy()->endOfMonth();

                return $leaveStart->lte($monthEnd) && $leaveEnd->gte($monthStart);
            });

            return [
                'id' => $employee->id,
                'name' => $employee->contact_name,
                'leaves' => $monthLeaves->map(function ($leave) {
                    $start = Carbon::parse($leave->start_date);
                    $end = Carbon::parse($leave->end_date);

                    return [
                        'id' => $leave->id,
                        'start_date' => $leave->start_date,
                        'end_date' => $leave->end_date,
                        'type' => $leave->type,
                        'type_label' => $leave->type_label,
                        'duration_days' => $leave->duration_days,
                        'reason' => $leave->reason,
                        'status' => $leave->status,
                    ];
                }),
            ];
        });

        $employeeOptions = $organisation->employees()
            ->where('state', 'working')
            ->orderBy('alias')
            ->get()
            ->map(fn ($employee) => [
                'value' => $employee->id,
                'label' => $employee->contact_name,
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
                'year' => (int) $year,
                'month' => (int) $month,
                'employee_id' => $employeeId ? (int) $employeeId : null,
                'type' => $type,
            ],
            'calendarData' => $calendarData,
            'daysInMonth' => $startOfMonth->daysInMonth,
            'monthName' => $startOfMonth->format('F'),
            'employeeOptions' => $employeeOptions,
            'typeOptions' => [
                ['value' => 'annual', 'label' => __('Annual Leave')],
                ['value' => 'medical', 'label' => __('Medical Leave')],
                ['value' => 'unpaid', 'label' => __('Unpaid Leave')],
            ],
            'type_options' => LeaveTypeEnum::labels(),
            'status_options' => LeaveStatusEnum::labels(),
        ]);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (string $routeName, array $routeParameters) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Dashboard'),
                        'icon'  => 'fal fa-bars',
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
