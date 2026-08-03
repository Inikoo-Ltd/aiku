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
use App\Models\HumanResources\EmployeeLeaveBalance;
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
                    'model'         => __('Contract :date', ['date' => $contract->start_date->format('d M Y')]),
                    'title'         => $employee->contact_name,
                    'icon'          => ['title' => __('Edit Contract'), 'icon' => 'fal fa-file-certificate'],
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
                            'fields' => $this->buildFields($contract, $employee),
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

    private function buildFields(EmployeeContract $contract, Employee $employee): array
    {
        $fields = [
            'start_date' => [
                'type'     => 'date',
                'label'    => __('Start date'),
                'required' => true,
                'value'    => $contract->start_date->toDateString(),
            ],
            'end_date' => [
                'type'  => 'date',
                'label' => __('End date'),
                'value' => $contract->end_date?->toDateString(),
            ],
            'annual_leave_days' => [
                'type'      => 'input',
                'inputType' => 'number',
                'label'     => __('Annual leave days'),
                'value'     => $contract->annual_leave_days,
            ],
            'notes' => [
                'type'  => 'textarea',
                'label' => __('Notes'),
                'value' => $contract->notes,
            ],
        ];

        $unlinkedBalances = EmployeeLeaveBalance::where('employee_id', $employee->id)
            ->whereNull('employee_contract_id')
            ->get(['id', 'annual_used', 'medical_used', 'unpaid_used']);

        if ($unlinkedBalances->isNotEmpty()) {
            $fields['link_balance_id'] = [
                'type'        => 'select',
                'label'       => __('Link existing leave balance'),
                'placeholder' => __('Keep current balance (default)'),
                'value'       => null,
                'options'     => $unlinkedBalances->map(fn (EmployeeLeaveBalance $b) => [
                    'value' => $b->id,
                    'label' => __('Balance: :annual annual used, :medical medical used', [
                        'annual'  => $b->annual_used,
                        'medical' => $b->medical_used,
                    ]),
                ])->toArray(),
                'mode' => 'single',
            ];
        }

        return $fields;
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
                        'label' => __('Contract :date', ['date' => $contract->start_date->format('d M Y')]),
                    ]
                ]
            ]
        );
    }
}
