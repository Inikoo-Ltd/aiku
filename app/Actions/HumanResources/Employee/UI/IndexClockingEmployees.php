<?php

namespace App\Actions\HumanResources\Employee\UI;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Clocking\ClockingEmployeesTabsEnum;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;

class IndexClockingEmployees extends OrgAction
{
    use AsAction;

    public function handle(ActionRequest $request): Response
    {

        if (!$this->tab) {
            $this->tab = ClockingEmployeesTabsEnum::SCAN_QR_CODE->value;
        }

        return Inertia::render(
            'Org/HumanResources/ClockingEmployees',
            [
                'title'       => __('Employee Clocking'),
                'breadcrumbs' => $this->getBreadcrumbs($request),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-user-clock'],
                        'title' => __('Employee Clocking')
                    ],
                    'title' => __('Clock In/Out'),
                    'model' => __('Clocking'),
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => ClockingEmployeesTabsEnum::navigation()
                ],


                ClockingEmployeesTabsEnum::SCAN_QR_CODE->value => $this->tab == ClockingEmployeesTabsEnum::SCAN_QR_CODE->value
                    ? fn () => [
                        'status' => 'ready_to_scan',
                    ]
                    : Inertia::lazy(fn () => ['status' => 'loaded_lazy']),

                ClockingEmployeesTabsEnum::TIMESHEETS->value => $this->tab == ClockingEmployeesTabsEnum::TIMESHEETS->value
                    ? fn () => [
                        'timesheets' => []
                    ]
                    : Inertia::lazy(fn () => ['status' => 'loaded_lazy']),
            ]
        );
    }

    public function asController(ActionRequest $request): Response
    {
        return $this->handle($request);
    }

    public function getBreadcrumbs(ActionRequest $request): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Employee Clocking'),
                        'route' => [
                            'name' => 'grp.clocking_employees.index'
                        ]
                    ]
                ]
            ]
        );
    }
}
