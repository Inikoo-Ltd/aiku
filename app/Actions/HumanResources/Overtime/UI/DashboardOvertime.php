<?php

namespace App\Actions\HumanResources\Overtime\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\OvertimeRequest;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Models\HumanResources\OvertimeType;

class DashboardOvertime extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithOvertimeSubNavigation;

    public function handle(Organisation $organisation, ActionRequest $request): Response
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);
        $employeeId = $request->input('employee_id');
        $overtimeTypeId = $request->input('overtime_type_id');

        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

        $employeesQuery = $organisation->employees()
            ->where('state', 'working')
            ->orderBy('contact_name');

        if ($employeeId) {
            $employeesQuery->where('id', $employeeId);
        }

        $employees = $employeesQuery->get();

        $overtimeRequestsQuery = OvertimeRequest::query()
            ->where('organisation_id', $organisation->id)
            ->where('status', 'approved')
            ->whereBetween('requested_date', [$startOfMonth, $endOfMonth])
            ->with(['overtimeType', 'employee', 'approver']);

        if ($overtimeTypeId) {
            $overtimeRequestsQuery->where('overtime_type_id', $overtimeTypeId);
        }

        if ($employeeId) {
            $overtimeRequestsQuery->where('employee_id', $employeeId);
        }

        $overtimeRequests = $overtimeRequestsQuery->get()->groupBy('employee_id');

        $calendarData = $employees->map(function (Employee $employee) use ($overtimeRequests) {
            return [
                'id' => $employee->id,
                'name' => $employee->contact_name,
                'overtimes' => $overtimeRequests->get($employee->id, collect())->map(function (OvertimeRequest $request) {
                    $requestedMinutes = $request->requested_duration_minutes;
                    $recordedMinutes = $request->recorded_duration_minutes;

                    return [
                        'id' => $request->id,
                        'date' => $request->requested_date->format('Y-m-d'),
                        'type_name' => $request->overtimeType->name,
                        'color' => $request->overtimeType->color,
                        'duration' => $requestedMinutes,
                        'formatted_duration' => floor($requestedMinutes / 60) . 'h ' . ($requestedMinutes % 60 > 0 ? ($requestedMinutes % 60) . 'm' : ''),
                        'reason' => $request->reason,
                        'status' => $request->status,
                        'start_time' => $request->requested_start_at ? $request->requested_start_at->format('H:i') : '-',
                        'end_time' => $request->requested_end_at ? $request->requested_end_at->format('H:i') : '-',
                        'recorded_start_time' => $request->recorded_start_at ? $request->recorded_start_at->format('H:i') : null,
                        'recorded_end_time' => $request->recorded_end_at ? $request->recorded_end_at->format('H:i') : null,
                        'recorded_duration' => $recordedMinutes,
                        'recorded_formatted_duration' => $recordedMinutes ? floor($recordedMinutes / 60) . 'h ' . ($recordedMinutes % 60 > 0 ? ($recordedMinutes % 60) . 'm' : '') : null,
                        'employee_name' => $request->employee->contact_name,
                        'approver_name' => $request->approver ? $request->approver->contact_name : '-',
                        'overtime_type' => $request->overtimeType->name,
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

        $overtimeTypeOptions = OvertimeType::query()
            ->where('organisation_id', $organisation->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($type) => [
                'value' => $type->id,
                'label' => $type->name,
            ]);

        return Inertia::render('Org/HumanResources/DashboardOvertime', [
            'breadcrumbs' => $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'title' => __('Overtime Dashboard'),
            'pageHead' => [
                'icon' => [
                    'icon' => ['fal', 'fa-user-hard-hat'],
                    'title' => __('Human resources')
                ],
                'iconRight' => [
                    'icon' => ['fal', 'fa-clock'],
                    'title' => __('Overtime')
                ],
                'title' => __('Overtime Dashboard'),
                'subNavigation' => $this->getOvertimeSubNavigation($request),
            ],
            'filters' => [
                'year' => (int) $year,
                'month' => (int) $month,
                'employee_id' => $employeeId ? (int) $employeeId : null,
                'overtime_type_id' => $overtimeTypeId ? (int) $overtimeTypeId : null,
            ],
            'calendarData' => $calendarData,
            'daysInMonth' => $startOfMonth->daysInMonth,
            'monthName' => $startOfMonth->format('F'),
            'employeeOptions' => $employeeOptions,
            'overtimeTypeOptions' => $overtimeTypeOptions,
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
            'grp.org.hr.overtime.dashboard' =>
            array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb($routeName, $routeParameters)
            ),
            default => [],
        };
    }
}
