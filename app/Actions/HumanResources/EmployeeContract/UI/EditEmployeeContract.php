<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\EmployeeContract\UI;

use App\Actions\HumanResources\WithEmployeeSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\EmployeeContract;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditEmployeeContract extends OrgAction
{
    use WithEmployeeSubNavigation;
    use WithHumanResourcesEditAuthorisation;

    public function handle(EmployeeContract $contract): EmployeeContract
    {
        return $contract;
    }

    public function asController(Organisation $organisation, Employee $employee, EmployeeContract $contract, ActionRequest $request): EmployeeContract
    {
        $this->initialisation($organisation, $request);

        return $this->handle($contract);
    }

    public function htmlResponse(EmployeeContract $contract, ActionRequest $request): Response
    {
        /** @var Employee $employee */
        $employee = $request->route('employee');

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit Contract'),
                'breadcrumbs' => $this->getBreadcrumbs($employee, $contract, $request->route()->originalParameters()),
                'pageHead'    => [
                    'model'         => __('Contract #:number', ['number' => $contract->contract_number]),
                    'title'         => $employee->contact_name,
                    'icon'          => ['title' => __('Edit Contract'), 'icon' => 'fal fa-file-contract'],
                    'subNavigation' => $this->getEmployeeSubNavigation($employee, $request),
                    'actions'       => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.org.hr.employees.show.contracts.index',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'label'  => __('Contract details'),
                            'fields' => [
                                'start_date' => [
                                    'type'     => 'date',
                                    'label'    => __('Start date'),
                                    'required' => true,
                                    'value'    => $contract->start_date->toDateString()
                                ],
                                'end_date' => [
                                    'type'  => 'date',
                                    'label' => __('End date'),
                                    'value' => $contract->end_date?->toDateString()
                                ],
                                'annual_leave_days' => [
                                    'type'      => 'input',
                                    'inputType' => 'number',
                                    'label'     => __('Annual leave days'),
                                    'value'     => $contract->annual_leave_days
                                ],
                                'notes' => [
                                    'type'  => 'textarea',
                                    'label' => __('Notes'),
                                    'value' => $contract->notes
                                ]
                            ]
                        ]
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.employee.contracts.update',
                            'parameters' => ['contract' => $contract->id]
                        ]
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(Employee $employee, EmployeeContract $contract, array $routeParameters): array
    {
        return array_merge(
            IndexEmployeeContracts::make()->getBreadcrumbs($employee, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.hr.employees.show.contracts.edit',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Contract #:number', ['number' => $contract->contract_number]),
                    ]
                ]
            ]
        );
    }
}
