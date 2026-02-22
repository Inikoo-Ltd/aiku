<?php

namespace App\Actions\HumanResources\AttendanceAdjustment\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\AttendanceAdjustment;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;

class DashboardAdjustments extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithAdjustmentSubNavigation;

    public function handle(Organisation $organisation, ActionRequest $request): Response
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);
        $employeeId = $request->input('employee_id');
        $status = $request->input('status');

        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

        $employeesQuery = $organisation->employees()
            ->where('state', 'working')
            ->orderBy('contact_name');

        if ($employeeId) {
            $employeesQuery->where('id', $employeeId);
        }

        $employees = $employeesQuery->get();

        $adjustmentsQuery = AttendanceAdjustment::query()
            ->where('organisation_id', $organisation->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->with(['employee']);

        if ($status) {
            $adjustmentsQuery->where('status', $status);
        }

        if ($employeeId) {
            $adjustmentsQuery->where('employee_id', $employeeId);
        }

        $adjustments = $adjustmentsQuery->get()->groupBy('employee_id');

        $calendarData = $employees->map(function (Employee $employee) use ($adjustments, $startOfMonth, $endOfMonth) {
            $employeeAdjustments = $adjustments->get($employee->id, collect());

            return [
                'id' => $employee->id,
                'name' => $employee->contact_name,
                'adjustments' => $employeeAdjustments->map(function ($adjustment) {
                    return [
                        'id' => $adjustment->id,
                        'date' => $adjustment->date->format('Y-m-d'),
                        'original_start_at' => $adjustment->original_start_at ? $adjustment->original_start_at->format('H:i') : null,
                        'original_end_at' => $adjustment->original_end_at ? $adjustment->original_end_at->format('H:i') : null,
                        'requested_start_at' => $adjustment->requested_start_at ? $adjustment->requested_start_at->format('H:i') : null,
                        'requested_end_at' => $adjustment->requested_end_at ? $adjustment->requested_end_at->format('H:i') : null,
                        'reason' => $adjustment->reason,
                        'status' => $adjustment->status->value,
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

        return Inertia::render('Org/HumanResources/DashboardAdjustments', [
            'breadcrumbs' => $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'title' => __('Attendance Adjustments Dashboard'),
            'pageHead' => [
                'icon' => [
                    'icon' => ['fal', 'fa-user-hard-hat'],
                    'title' => __('Human resources')
                ],
                'iconRight' => [
                    'icon' => ['fal', 'fa-clock'],
                    'title' => __('Adjustments')
                ],
                'title' => __('Attendance Adjustments Dashboard'),
                'subNavigation' => $this->getAdjustmentSubNavigation($request),
            ],
            'filters' => [
                'year' => (int) $year,
                'month' => (int) $month,
                'employee_id' => $employeeId ? (int) $employeeId : null,
                'status' => $status,
            ],
            'calendarData' => $calendarData,
            'daysInMonth' => $startOfMonth->daysInMonth,
            'monthName' => $startOfMonth->format('F'),
            'employeeOptions' => $employeeOptions,
            'statusOptions' => [
                ['value' => 'pending', 'label' => __('Pending')],
                ['value' => 'approved', 'label' => __('Approved')],
                ['value' => 'rejected', 'label' => __('Rejected')],
            ],
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
            'grp.org.hr.adjustments.dashboard' =>
            array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb($routeName, $routeParameters)
            ),
            default => [],
        };
    }
}
