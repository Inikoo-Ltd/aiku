<?php

namespace App\Actions\HumanResources\EmployeeAnalytics\UI;

use App\Actions\HumanResources\WithEmployeeSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use App\Services\EmployeeAnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowEmployeeAnalytics extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithEmployeeSubNavigation;

    public function handle(Employee $employee, Carbon $startDate, Carbon $endDate): array
    {
        $service = new EmployeeAnalyticsService();

        $attendance = $service->calculateAttendanceMetrics($employee, $startDate, $endDate);
        $leave = $service->calculateLeaveMetrics($employee, $startDate, $endDate);
        $summary = $service->calculateSummaryMetrics($attendance, $leave);

        return [
            'employee'    => [
                'id'           => $employee->id,
                'slug'         => $employee->slug,
                'contact_name' => $employee->contact_name,
                'worker_number'=> $employee->worker_number,
            ],
            'attendance'  => $attendance,
            'leave'       => $leave,
            'summary'     => $summary,
            'period'      => [
                'start' => $startDate->format('Y-m-d'),
                'end'   => $endDate->format('Y-m-d'),
            ],
        ];
    }

    public function asController(Organisation $organisation, Employee $employee, ActionRequest $request): Response
    {
        if (!config('employee-analytics.enabled', false)) {
            abort(404, 'Employee Analytics feature is not enabled.');
        }

        $this->initialisation($organisation, $request);

        $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()->format('Y-m-d')));
        $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()->format('Y-m-d')));

        $analytics = $this->handle($employee, $startDate, $endDate);

        return Inertia::render(
            'Org/HumanResources/EmployeeAnalyticsShow',
            [
                'title'          => __('Employee Analytics'),
                'breadcrumbs'    => $this->getBreadcrumbs($employee, $request->route()->originalParameters()),
                'navigation'     => [
                    'previous' => $this->getPrevious($employee, $request),
                    'next'     => $this->getNext($employee, $request),
                ],
                'pageHead'       => [
                    'icon'           => [
                        'icon'  => 'fal fa-chart-bar',
                        'title' => __('Analytics'),
                    ],
                    'model'          => __('Employee'),
                    'title'          => $employee->contact_name,
                    'subNavigation'  => $this->getEmployeeSubNavigation($employee, $request),
                ],
                'filters'        => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date'   => $endDate->format('Y-m-d'),
                ],
                'analytics'      => $analytics,
                'leaveTypes'     => [
                    'annual'  => __('Annual Leave'),
                    'medical' => __('Medical Leave'),
                    'unpaid'  => __('Unpaid Leave'),
                ],
            ]
        );
    }

    public function getBreadcrumbs(Employee $employee, array $routeParameters): array
    {
        return array_merge(
            (new ShowHumanResourcesDashboard())->getBreadcrumbs($routeParameters),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.hr.analytics.index',
                                'parameters' => Arr::only($routeParameters, 'organisation'),
                            ],
                            'label' => __('Analytics'),
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.hr.analytics.show',
                                'parameters' => $routeParameters,
                            ],
                            'label' => $employee->slug,
                        ],
                    ],
                ],
            ]
        );
    }

    public function getPrevious(Employee $employee, ActionRequest $request): ?array
    {
        $previous = Employee::where('slug', '<', $employee->slug)
            ->where('organisation_id', $this->organisation->id)
            ->orderBy('slug', 'desc')
            ->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Employee $employee, ActionRequest $request): ?array
    {
        $next = Employee::where('slug', '>', $employee->slug)
            ->where('organisation_id', $this->organisation->id)
            ->orderBy('slug')
            ->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Employee $employee, string $routeName): ?array
    {
        if (!$employee) {
            return null;
        }

        return [
            'label' => $employee->contact_name,
            'route' => [
                'name'       => $routeName,
                'parameters' => [
                    'organisation' => $this->organisation->slug,
                    'employee'     => $employee->slug,
                ],
            ],
        ];
    }
}
