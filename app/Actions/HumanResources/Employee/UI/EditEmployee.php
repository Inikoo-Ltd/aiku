<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Mar 2023 19:14:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\HumanResources\Employee\GetEmployeeJobPositionsData;
use App\Actions\HumanResources\WithEmployeeSubNavigation;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\User\GetUserGroupScopeJobPositionsData;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Http\Resources\Catalogue\ShopResource;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Http\Resources\HumanResources\JobPositionResource;
use App\Http\Resources\Inventory\WarehouseResource;
use App\Http\Resources\SysAdmin\Organisation\OrganisationsResource;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditEmployee extends OrgAction
{
    use WithEmployeeSubNavigation;

    protected Organisation $organisation;

    public function handle(Employee $employee): Employee
    {
        return $employee;
    }

    public function asController(Organisation $organisation, Employee $employee, ActionRequest $request): Employee
    {
        $this->organisation = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($employee);
    }

    /**
     * @throws Exception
     */
    public function htmlResponse(Employee $employee, ActionRequest $request): Response
    {
        $user                         = $employee->getUser();
        $jobPositionsOrganisationData = GetEmployeeJobPositionsData::run($employee);
        $jobPositionsGroupData        = GetUserGroupScopeJobPositionsData::run($user);

        $sections['properties'] = [
            'label'  => __('Properties'),
            'icon'   => 'fal fa-sliders-h',
            'fields' => [
                'worker_number' => [
                    'type'     => 'input',
                    'label'    => __('worker number'),
                    'required' => true,
                    'value'    => $employee->worker_number
                ],
                'alias'         => [
                    'type'     => 'input',
                    'label'    => __('alias'),
                    'required' => true,
                    'value'    => $employee->alias
                ],
                'work_email'    => [
                    'type'  => 'input',
                    'label' => __('work email'),
                    'value' => $employee->work_email ?? ''
                ],
                'cluster'       => [
                    'type'     => 'employeeState',
                    'mode'     => 'card',
                    'label'    => 'Employee status',
                    'required' => true,
                    'options'  => [
                        [
                            'title'       => __('Hired'),
                            'description' => __('Will start in future date'),
                            'value'       => EmployeeStateEnum::HIRED->value
                        ],
                        [
                            'title'       => __('Working'),
                            'description' => __('Employee already working'),
                            'value'       => EmployeeStateEnum::WORKING->value
                        ],
                        [
                            'title'       => __('Leaving'),
                            'description' => __('Employee will leave'),
                            'value'       => EmployeeStateEnum::LEAVING->value
                        ],
                        [
                            'title'       => __('Left'),
                            'description' => __('Employee already left the office'),
                            'value'       => EmployeeStateEnum::LEFT->value
                        ],
                    ],
                    'value'    => [
                        'state'               => $employee->state,
                        'employment_start_at' => $employee->employment_start_at ?? '',
                        'employment_end_at'   => $employee->employment_end_at ?? '',
                    ]
                ],
                'job_title'     => [
                    'type'        => 'input',
                    'label'       => __('job title'),
                    'placeholder' => __('Job title'),
                    'searchable'  => true,
                    'value'       => $employee->job_title,
                    'required'    => true
                ],


            ]
        ];


        $organisations    = Organisation::where('id', $employee->organisation_id)->get();
        $organisationList = OrganisationsResource::collection($organisations);

        if ($user) {
            $sections['job_positions'] = [
                'label'  => __('Job Positions (permissions)'),
                'icon'   => 'fal fa-clipboard-list',
                'fields' => [
                    'positions' => [
                        'type'                          => 'permissions',
                        "noSaveButton"                  => true,
                        'required'                      => true,
                        'label'                         => __('Job Positions (permissions)'),
                        'options'                       => [
                            $employee->organisation->slug => [
                                'positions'   => JobPositionResource::collection($this->organisation->jobPositions),
                                'shops'       => ShopResource::collection($this->organisation->shops()->where('type', '!=', ShopTypeEnum::FULFILMENT)->get()),
                                'fulfilments' => ShopResource::collection($this->organisation->shops()->where('type', '=', ShopTypeEnum::FULFILMENT)->get()),
                                'warehouses'  => WarehouseResource::collection($this->organisation->warehouses),
                            ],
                        ],
                        'is_in_organisation'            => true,  // To remove parameter
                        'organisation_list'             => $organisationList,
                        'updatePseudoJobPositionsRoute' => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.user.group_permissions.update',
                            'parameters' => [
                                'user' => $user->id
                            ]
                        ],
                        'updateJobPositionsRoute'       => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.employee.update',
                            'parameters' => [
                                'employee' => $employee->id
                            ]
                        ],
                        'value'                         => [
                            'group'         => $jobPositionsGroupData,
                            'organisations' => [
                                $employee->organisation->slug => $jobPositionsOrganisationData,
                            ],
                        ],
                        // 'value' => $jobPositionsOrganisationData,
                        'full'                          => true
                    ],

                ]
            ];
            $sections['credentials']   = [
                'label'  => __('Credentials'),
                'icon'   => 'fal fa-key',
                'fields' => [
                    'username' => [
                        'type'  => 'input',
                        'label' => __('username'),
                        'value' => $user->username

                    ],
                    'password' => [
                        'type'  => 'password',
                        'label' => __('password'),

                    ],
                ]
            ];
        }

        $sections['personal'] = [
            'label'  => __('Personal information'),
            'icon'   => 'fal fa-id-card',
            'fields' => [
                'contact_name'             => [
                    'type'        => 'input',
                    'label'       => __('name'),
                    'placeholder' => __('Name'),
                    'value'       => $employee->contact_name,
                    'required'    => true
                ],
                'date_of_birth'            => [
                    'type'        => 'date',
                    'label'       => __('date of birth'),
                    'placeholder' => __('Date of birth'),
                    'value'       => $employee->date_of_birth
                ],
                'email'                    => [
                    'type'  => 'input',
                    'label' => __('personal email'),
                    'value' => $employee->email
                ],
                'contact_address'          => [
                    'type'    => 'address',
                    'label'   => __('Address'),
                    'value'   => AddressFormFieldsResource::make($employee->address)->getArray(),
                    'options' => [
                        'countriesAddressData' => GetAddressData::run()
                    ]
                ],
                'emergency_contact'        => [
                    'type'  => 'textarea',
                    'label' => __('Emergency Contact'),
                    'value' => $employee->emergency_contact
                ],
                'identity_document_type'   => [
                    'type'  => 'input',
                    'label' => __('identity document type'),
                    'value' => $employee->identity_document_type
                ],
                'identity_document_number' => [
                    'type'  => 'input',
                    'label' => __('identity document number'),
                    'value' => $employee->identity_document_number
                ],
                'notes'                    => [
                    'type'  => 'textarea',
                    'label' => __('notes'),
                    'value' => $employee->notes
                ],
            ]
        ];

        $sections['pin'] = [
            'label'  => __('Clocking PIN'),
            'icon'   => 'fal fa-chess-clock',
            'fields' => [
                'pin' => [
                    'type'           => 'pin',
                    'label'          => __('pin'),
                    'route_generate' => [
                        'name'       => 'grp.org.hr.employees.generate-pin',
                        'parameters' => [$employee->organisation->slug, $employee->slug]
                    ],
                    'value'          => $employee->pin
                ],
            ]
        ];

        $currentSection = 'properties';
        if ($request->has('section') && Arr::has($sections, $request->get('section'))) {
            $currentSection = $request->get('section');
        }


        return Inertia::render(
            'EditModel',
            [
                'live_users'  => [
                    'icon_left'  => [
                        'icon'  => 'fal fa-user-hard-hat',
                        'class' => 'text-lime-400'
                    ],
                    'icon_right' => [
                        'icon'  => 'fal fa-pencil',
                        'class' => 'text-gray-300'
                    ],
                ],
                'title'       => __('employee'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $employee,
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'         => $employee->contact_name,
                    'model'         => __('Edit Employee'),
                    'subNavigation' => $this->getEmployeeSubNavigation($employee, $request),
                    'icon'          => 'fal fa-user-hard-hat',
                    'actions'       => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'current'   => $currentSection,
                    'blueprint' => $sections,
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.employee.update',
                            'parameters' => [$employee->id]

                        ],
                    ]

                ],

            ]
        );
    }

    public function getBreadcrumbs(Employee $employee, array $routeParameters): array
    {
        return ShowEmployee::make()->getBreadcrumbs($employee, routeParameters: $routeParameters, suffix: '('.__('Editing').')');
    }
}
