<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Created: 2026-11-06
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\HumanResources\EmployeeContract\UI;

use App\Actions\HumanResources\WithEmployeeSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\EmployeeLeaveBalance;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateEmployeeContract extends OrgAction
{
    use WithEmployeeSubNavigation;
    use WithHumanResourcesEditAuthorisation;

    public function handle(Employee $employee): Employee
    {
        return $employee;
    }

    public function asController(Organisation $organisation, Employee $employee, ActionRequest $request): Employee
    {
        $this->initialisation($organisation, $request);

        return $this->handle($employee);
    }

    public function htmlResponse(Employee $employee, ActionRequest $request): Response
    {
        $unlinkedBalances = EmployeeLeaveBalance::where('employee_id', $employee->id)
            ->whereNull('employee_contract_id')
            ->get(['id', 'annual_used', 'medical_used', 'unpaid_used']);

        $contractFields = [
            'start_date' => [
                'type'     => 'date',
                'label'    => __('Start date'),
                'required' => true,
                'value'    => '',
            ],
            'end_date' => [
                'type'  => 'date',
                'label' => __('End date'),
                'value' => '',
            ],
            'annual_leave_days' => [
                'type'      => 'input',
                'inputType' => 'number',
                'label'     => __('Annual leave days'),
                'value'     => $employee->organisation->getDefaultAnnualLeaveDays(),
            ],
            'notes' => [
                'type'  => 'textarea',
                'label' => __('Notes'),
                'value' => '',
            ],
        ];

        if ($unlinkedBalances->isNotEmpty()) {
            $contractFields['link_balance_id'] = [
                'type'        => 'select',
                'label'       => __('Link existing leave balance'),
                'placeholder' => __('Create new balance (default)'),
                'value'       => null,
                'options'     => $unlinkedBalances->map(fn (EmployeeLeaveBalance $b) => [
                    'value' => $b->id,
                    'label' => __('Balance: :annual annual used, :medical medical used', [
                        'annual'  => $b->annual_used,
                        'medical' => $b->medical_used,
                    ]),
                ])->toArray(),
                'mode'        => 'single',
            ];
        }

        return Inertia::render(
            'CreateModel',
            [
                'title'       => __('New Contract'),
                'breadcrumbs' => $this->getBreadcrumbs($employee, $request->route()->originalParameters()),
                'pageHead'    => [
                    'model'         => __('Employee'),
                    'title'         => $employee->contact_name,
                    'icon'          => ['title' => __('New Contract'), 'icon' => 'fal fa-file-certificate'],
                    'subNavigation' => $this->getEmployeeSubNavigation($employee, $request),
                    'actions'       => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'route' => [
                                'name'       => 'grp.org.hr.employees.show.contracts.index',
                                'parameters' => $request->route()->originalParameters(),
                            ],
                        ]
                    ],
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'label'  => __('Contract details'),
                            'fields' => $contractFields,
                        ]
                    ],
                    'route' => [
                        'name'       => 'grp.models.employee.contracts.store',
                        'parameters' => ['employee' => $employee->id],
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(Employee $employee, array $routeParameters): array
    {
        return array_merge(
            IndexEmployeeContracts::make()->getBreadcrumbs($employee, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.hr.employees.show.contracts.create',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Add contract'),
                    ]
                ]
            ]
        );
    }
}
