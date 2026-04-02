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
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Enums\HumanResources\Employee\EmploymentTypeEnum;
use App\Http\Resources\Catalogue\ShopResource;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Http\Resources\Helpers\EmergencyFormFieldsResource;
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
    use WithHumanResourcesEditAuthorisation;

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
        $user = $employee->getUser();
        $jobPositionsOrganisationData = GetEmployeeJobPositionsData::run($employee);
        $jobPositionsGroupData = GetUserGroupScopeJobPositionsData::run($user);
        $latestContract = $employee->getMedia('contracts')->sortByDesc('id')->first();

        $sections['properties'] = [
            'label' => __('Properties'),
            'icon' => 'fal fa-sliders-h',
            'fields' => [
                'worker_number' => [
                    'type' => 'input',
                    'label' => __('Worker number'),
                    'required' => true,
                    'value' => $employee->worker_number
                ],
                'alias' => [
                    'type' => 'input',
                    'label' => __('Alias'),
                    'required' => true,
                    'value' => $employee->alias
                ],
                'work_email' => [
                    'type' => 'input',
                    'label' => __('Work email'),
                    'value' => $employee->work_email ?? ''
                ],

                'state' => [
                    'type' => 'select',
                    'label' => 'Employee Status',
                    'required' => true,
                    'options' => [
                        ['value' => 'hired', 'label' => __('Hired'), 'description' => __('Employee has been hired and awaiting onboarding')],
                        ['value' => 'working', 'label' => __('Working'), 'description' => __('Employee is actively working')],
                        ['value' => 'leaving', 'label' => __('Leaving'), 'description' => __('Employee has resigned and is in notice period')],
                        ['value' => 'left', 'label' => __('Left'), 'description' => __('Employee has left the organization')],
                    ],
                    'value' => $employee->state?->value ?? 'working',
                ],

                'job_title' => [
                    'type' => 'select',
                    'label' => __('Job Title'),
                    'placeholder' => __('Job Title'),
                    'searchable' => true,
                    'options' => [
                        ['label' => 'Developer - Front End', 'value' => 'developer-front-end'],
                        ['label' => 'Developer - Back End', 'value' => 'developer-back-end'],
                        ['label' => 'Developer - Full Stack', 'value' => 'developer-full-stack'],
                        ['label' => 'Marketing - Social Media Specialist', 'value' => 'marketing-social-media-specialist'],
                        ['label' => 'Marketing - PPC', 'value' => 'marketing-ppc'],
                        ['label' => 'Marketing - SEO', 'value' => 'marketing-seo'],
                        ['label' => 'Managerial - Manager Officer', 'value' => 'managerial-manager-officer'],
                        ['label' => 'Managerial - Finance & Accounting', 'value' => 'managerial-finance-and-accounting'],
                    ],
                    'required' => true
                ],

                'type' => [
                    'type' => 'select',
                    'label' => __('Worker Type'),
                    'required' => true,
                    'options' => [
                        ['value' => EmployeeTypeEnum::EMPLOYEE->value, 'label' => __('Employee')],
                        ['value' => EmployeeTypeEnum::INTERNSHIP->value, 'label' => __('Internship')],
                        ['value' => EmployeeTypeEnum::VOLUNTEER->value, 'label' => __('Volunteer')],
                        ['value' => EmployeeTypeEnum::TEMPORAL_WORKER->value, 'label' => __('Temporal Worker')],
                        ['value' => EmployeeTypeEnum::FREELANCE->value, 'label' => __('Freelance')],
                    ],
                    'value' => $employee->type?->value ?? EmployeeTypeEnum::EMPLOYEE->value,
                ],

                'employment_type' => [
                    'type' => 'select',
                    'label' => __('Employment Type'),
                    'required' => true,
                    'options' => [
                        ['value' => EmploymentTypeEnum::FULLTIME->value, 'label' => __('Full Time')],
                        ['value' => EmploymentTypeEnum::PARTTIME->value, 'label' => __('Part Time')],
                    ],
                    'value' => $employee->employment_type?->value ?? EmploymentTypeEnum::FULLTIME->value,
                ],

                'probation_period_days' => [
                    'type' => 'input',
                    'label' => __('Probation Period (Days)'),
                    'placeholder' => __('90'),
                    'value' => $employee->probation_period_days ?? 90,
                ],


            ]
        ];


        $organisations = Organisation::where('id', $employee->organisation_id)->get();
        $organisationList = OrganisationsResource::collection($organisations);

        if ($user) {
            $sections['job_positions'] = [
                'label' => __('Job Positions (permissions)'),
                'icon' => 'fal fa-clipboard-list',
                'fields' => [
                    'positions' => [
                        'type' => 'permissions',
                        "noSaveButton" => true,
                        'required' => true,
                        'label' => __('Job Positions (permissions)'),
                        'options' => [
                            $employee->organisation->slug => [
                                'positions' => JobPositionResource::collection($this->organisation->jobPositions),
                                'shops' => ShopResource::collection($this->organisation->shops()->where('type', '!=', ShopTypeEnum::FULFILMENT)->get()),
                                'fulfilments' => ShopResource::collection($this->organisation->shops()->where('type', '=', ShopTypeEnum::FULFILMENT)->get()),
                                'warehouses' => WarehouseResource::collection($this->organisation->warehouses),
                            ],
                        ],
                        'is_in_organisation' => true,  // To remove parameter
                        'organisation_list' => $organisationList,
                        'updatePseudoJobPositionsRoute' => [
                            'method' => 'patch',
                            'name' => 'grp.models.user.group_permissions.update',
                            'parameters' => [
                                'user' => $user->id
                            ]
                        ],
                        'updateJobPositionsRoute' => [
                            'method' => 'patch',
                            'name' => 'grp.models.employee.update',
                            'parameters' => [
                                'employee' => $employee->id
                            ]
                        ],
                        'value' => [
                            'group' => $jobPositionsGroupData,
                            'organisations' => [
                                $employee->organisation->slug => $jobPositionsOrganisationData,
                            ],
                        ],
                        'full' => true
                    ],

                ]
            ];
            $sections['credentials'] = [
                'label' => __('Credentials'),
                'icon' => 'fal fa-key',
                'fields' => [
                    'username' => [
                        'type' => 'input',
                        'label' => __('Username'),
                        'value' => $user->username

                    ],
                    'password' => [
                        'type' => 'password',
                        'label' => __('Password'),

                    ],
                ]
            ];
        }

        $sections['personal'] = [
            'label' => __('Personal information'),
            'icon' => 'fal fa-id-card',
            'fields' => [
                'contact_name' => [
                    'type' => 'input',
                    'label' => __('Name'),
                    'placeholder' => __('Name'),
                    'value' => $employee->contact_name,
                    'required' => true
                ],
                'date_of_birth' => [
                    'type' => 'date',
                    'label' => __('Date Of Birth'),
                    'placeholder' => __('Date of birth'),
                    'value' => $employee->date_of_birth
                ],
                'email' => [
                    'type' => 'input',
                    'label' => __('Personal Email'),
                    'value' => $employee->email
                ],
                'gender' => [
                    'type' => 'select',
                    'label' => __('Employee Gender'),
                    'placeholder' => __('Select Gender'),
                    'value' => $employee->gender?->value ?? $employee->gender,
                    'mode' => 'single',
                    'searchable' => true,
                    'options' => [
                        ['title' => __('Male'), 'value' => 'male', 'label' => __('Male')],
                        ['title' => __('Female'), 'value' => 'female', 'label' => __('Female')],
                        ['title' => __('Other'), 'value' => 'other', 'label' => __('Other')],
                    ]
                ],
                'contact_address' => [
                    'type' => 'address',
                    'label' => __('Address'),
                    'value' => AddressFormFieldsResource::make($employee->address)->getArray(),
                    'options' => [
                        'countriesAddressData' => GetAddressData::run()
                    ]
                ],
                'insurance_number' => [
                    'type' => 'input',
                    'placeholder' => __('Your Insurance Number'),
                    'label' => __('Insurance Number'),
                    'value' => $employee->insurance_number,
                ],
                'bank_account_name' => [
                    'type' => 'input',
                    'label' => __('Bank Account Name'),
                    'value' => $employee->bank_account_name,
                ],
                'bank_account_number' => [
                    'type' => 'input',
                    'label' => __('Bank Account Number'),
                    'value' => $employee->bank_account_number,
                ],
                'religion' => [
                    'type' => 'select',
                    'required' => true,
                    'label' => __('Religion'),
                    'mode' => 'single',
                    'value' => $employee->religion,
                    'placeholder' => __('Select Religion'),
                    'options' => [
                        [
                            'title' => 'Islam',
                            'value' => 'Islam',
                            'label' => __('Islam')
                        ],
                        [
                            'title' => 'Christianity',
                            'value' => 'Christianity',
                            'label' => __('Christianity'),
                        ],
                        [
                            'title' => 'Catholicism',
                            'value' => 'Catholicism',
                            'label' => __('Catholicism')
                        ],
                        [
                            'title' => 'Hinduism',
                            'value' => 'Hinduism',
                            'label' => __('Hinduism')
                        ],
                        [
                            'title' => 'Buddhism',
                            'value' => 'Buddhism',
                            'label' => __('Buddhism')
                        ],
                        [
                            'title' => 'Confucianism',
                            'value' => 'Confucianism',
                            'label' => 'Confucianism',
                        ],
                        [
                            'title' => 'Other',
                            'value' => 'Other',
                            'label' => 'Other',
                        ],
                    ],
                ],
                'emergency_contact' => [
                    'type' => 'emergency_contact',
                    'label' => __('Emergency Contact'),
                    'value' => EmergencyFormFieldsResource::make($employee->emergency_contact)->getArray(),
                    'options' => [
                        //
                    ],
                ],
                'identity_document_type' => [
                    'type' => 'input',
                    'label' => __('Identity Document Type'),
                    'value' => $employee->identity_document_type
                ],
                'identity_document_number' => [
                    'type' => 'input',
                    'label' => __('Identity Document Number'),
                    'value' => $employee->identity_document_number
                ],
                'notes' => [
                    'type' => 'textarea',
                    'label' => __('notes'),
                    'value' => $employee->notes
                ],
            ]
        ];

        $sections['pin'] = [
            'label' => __('Clocking PIN'),
            'icon' => 'fal fa-chess-clock',
            'fields' => [
                'pin' => [
                    'type' => 'pin',
                    'label' => __('pin'),
                    'route_generate' => [
                        'name' => 'grp.org.hr.employees.generate-pin',
                        'parameters' => [$employee->organisation->slug, $employee->slug]
                    ],
                    'value' => $employee->pin
                ],
            ]
        ];

        $sections['contract'] = [
            'label' => __('Contract'),
            'icon' => 'fal fa-file-invoice',
            'fields' => [
                'contract_start_date' => [
                    'type' => 'date',
                    'label' => __('Contract Start Date'),
                    'value' => $employee->contract_start_date,
                ],
                'contract_end_date' => [
                    'type' => 'date',
                    'label' => __('Contract End Date'),
                    'value' => $employee->contract_end_date,
                ],
                'contract_document' => [
                    'type' => 'file_upload',
                    'label' => __('Contract Document'),
                    'accept' => 'application/pdf',
                    'warning' => $latestContract ? __('Current file: :file', ['file' => $latestContract->file_name]) : null,
                    'preview_url' => $latestContract ? route('grp.media.show', ['media' => $latestContract->ulid]) : null,
                    'preview_label' => __('Preview current contract'),
                    'value' => null,
                    'updateRoute' => [
                        'name' => 'grp.models.employee.contract.upload',
                        'parameters' => [
                            'employee' => $employee->id
                        ],
                    ],
                ],
            ],
        ];

        $sections['Leave Balance'] = [
            'label' => __('Leave Balance'),
            'icon' => 'fal fa-clock',
            'fields' => [
                'annual_days' => [
                    'type' => 'input',
                    'label' => __('Annual Leave Balance'),
                    'value' => $employee->leaveBalance?->annual_days ?? $employee->organisation->getDefaultAnnualLeaveDays(),
                ],
                'annual_used' => [
                    'type' => 'input',
                    'label' => __('Annual Leave Used'),
                    'value' => $employee->leaveBalance?->annual_used ?? 0,
                    'disabled' => true,
                ],
                'annual_remaining' => [
                    'type' => 'input',
                    'label' => __('Annual Leave Remaining'),
                    'value' => $employee->leaveBalance?->annual_remaining ?? $employee->organisation->getDefaultAnnualLeaveDays(),
                    'disabled' => true,
                ],
                'unpaid_used' => [
                    'type' => 'input',
                    'label' => __('Unpaid Leave Used'),
                    'value' => $employee->leaveBalance?->unpaid_used ?? 0,
                    'disabled' => true,
                ],
            ]
        ];

        $currentSection = 'properties';
        if ($request->has('section') && Arr::has($sections, $request->input('section'))) {
            $currentSection = $request->input('section');
        }

        return Inertia::render(
            'EditModel',
            [
                'live_users' => [
                    'icon_left' => [
                        'icon' => 'fal fa-user-hard-hat',
                        'class' => 'text-lime-400'
                    ],
                    'icon_right' => [
                        'icon' => 'fal fa-pencil',
                        'class' => 'text-gray-300'
                    ],
                ],
                'title' => __('Employee'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $employee,
                    $request->route()->originalParameters()
                ),
                'pageHead' => [
                    'title' => $employee->contact_name,
                    'model' => __('Edit Employee'),
                    'subNavigation' => $this->getEmployeeSubNavigation($employee, $request),
                    'icon' => 'fal fa-user-hard-hat',
                    'actions' => [
                        [
                            'type' => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name' => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'current' => $currentSection,
                    'blueprint' => $sections,
                    'args' => [
                        'updateRoute' => [
                            'name' => 'grp.models.employee.update',
                            'parameters' => [$employee->id]

                        ],
                    ]

                ],

            ]
        );
    }

    public function getBreadcrumbs(Employee $employee, array $routeParameters): array
    {
        return ShowEmployee::make()->getBreadcrumbs($employee, routeParameters: $routeParameters, suffix: '(' . __('Editing') . ')');
    }
}
