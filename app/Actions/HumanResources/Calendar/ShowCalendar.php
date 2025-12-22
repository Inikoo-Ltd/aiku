<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Mar 2023 19:13:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Calendar;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Enums\UI\HumanResources\EmployeeTabsEnum;
use App\Http\Resources\HumanResources\EmployeeResource;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowCalendar extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    public function handle(Employee $employee): Employee
    {
        return $employee;
    }

    public function asController(Organisation $organisation, Employee $employee, ActionRequest $request): Employee
    {
        $this->initialisation($organisation, $request)->withTab(EmployeeTabsEnum::values());

        return $this->handle($employee);
    }

    public function htmlResponse(Employee $employee, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/Calendar',
            [
                'title'       => __('Calendar'),
                'breadcrumbs' => $this->getBreadcrumbs($employee, $request->route()->originalParameters()),

                'pageHead'    => [
                    'title' => $employee->worker_number,
                    'meta'  => [
                        [
                            'name'     => $employee->contact_name,
                            'leftIcon' => [
                                'icon'    => 'fal fa-id-card',
                                'tooltip' => __('Worker number')
                            ]
                        ],

                        $employee->getUser() ?
                            [
                                'name'     => $employee->getUser()?->username,
                                'leftIcon' => [
                                    'icon'    => 'fal fa-user',
                                    'tooltip' => __('User')
                                ]
                            ] : []
                    ],
                    'edit'  => $this->canEdit ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters())
                        ]
                    ] : false,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => EmployeeTabsEnum::navigation()
                ]
            ]
        );
    }


    public function jsonResponse(Employee $employee): EmployeeResource
    {
        return new EmployeeResource($employee);
    }

    public function getBreadcrumbs(Employee $employee, array $routeParameters, $suffix = null): array
    {
        return array_merge(
            ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name' => 'grp.org.hr.employees.index',
                            ],
                            'label' => __('Employees')
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.hr.employees.show',
                                'parameters' => [$employee->slug]
                            ],
                            'label' => $employee->worker_number,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ]
        );
    }


}
