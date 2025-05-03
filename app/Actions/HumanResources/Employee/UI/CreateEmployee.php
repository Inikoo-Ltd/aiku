<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Mar 2023 19:09:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Http\Resources\HumanResources\JobPositionResource;
use App\Http\Resources\Inventory\WarehouseResource;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Catalogue\ShopResource;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\Helpers\Address;
use App\Models\SysAdmin\Organisation;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateEmployee extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Organisation $organisation, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('new employee'),
                'pageHead'    => [
                    'title'   => __('new employee'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.org.hr.employees.index',
                                'parameters' => $request->route()->originalParameters()
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('personal information'),
                            'fields' => [
                                'contact_name'  => [
                                    'type'     => 'input',
                                    'label'    => __('name'),
                                    'required' => true,
                                ],
                                'date_of_birth' => [
                                    'type'  => 'date',
                                    'label' => __('date of birth'),
                                    'value' => ''
                                ],
                                'email'         => [
                                    'type'  => 'input',
                                    'label' => __('personal email'),
                                    'value' => ''
                                ],
                                'phone'         => [
                                    'type'  => 'input',
                                    'label' => __('contact number'),
                                    'value' => ''
                                ],
                                'contact_address'      => [
                                    'type'    => 'address',
                                    'label'   => __('Address'),
                                    'value'   => AddressFormFieldsResource::make(
                                        new Address(
                                            [
                                                'country_id' => $organisation->country_id,

                                            ]
                                        )
                                    )->getArray(),
                                    'options' => [
                                        'countriesAddressData' => GetAddressData::run()

                                    ]
                                ],
                                'emergency_contact'         => [
                                    'type'  => 'textarea',
                                    'label' => __('emergency contact'),
                                    'value' => ''
                                ],
                                'identity_document_type'         => [
                                    'type'  => 'input',
                                    'label' => __('identity document type'),
                                    'value' => ''
                                ],
                                'identity_document_number'         => [
                                    'type'  => 'input',
                                    'label' => __('identity document number'),
                                    'value' => ''
                                ],
                                'notes'         => [
                                    'type'  => 'textarea',
                                    'label' => __('notes'),
                                    'value' => ''
                                ],

                            ]
                        ],
                        [
                            'title'  => __('Employment'),
                            'fields' => [

                                'type'               => [
                                    'label'     => __('Type'),
                                    'type'      => 'radio',
                                    'mode'      => 'compact',
                                    'valueProp' => 'value',
                                    'required'  => true,
                                    'value'     => EmployeeTypeEnum::EMPLOYEE->value,
                                    'options'   => [
                                        [
                                            'title'       => __('employee'),
                                            'value'       => EmployeeTypeEnum::EMPLOYEE->value
                                        ],
                                        [
                                            'title'       => __('volunteer'),
                                            'value'       => EmployeeTypeEnum::VOLUNTEER->value
                                        ],
                                        [
                                            'title'       => __('temporal worker'),
                                            'value'       => EmployeeTypeEnum::TEMPORAL_WORKER->value
                                        ],
                                        [
                                            'title'       => __('work experience'),
                                            'value'       => EmployeeTypeEnum::WORK_EXPERIENCE->value
                                        ],

                                    ]
                                ],

                                'worker_number'       => [
                                    'type'     => 'input',
                                    'label'    => __('worker number'),
                                    'required' => true,
                                    'value'    => ''
                                ],
                                'alias'               => [
                                    'type'     => 'input',
                                    'label'    => __('alias'),
                                    'required' => true,
                                    'value'    => ''
                                ],
                                'work_email'          => [
                                    'type'  => 'input',
                                    'label' => __('work email'),
                                    'value' => ''
                                ],
                                'state'               => [
                                    'label'     => __('state'),
                                    'type'      => 'radio',
                                    'mode'      => 'card',
                                    'valueProp' => 'value',
                                    'required'  => true,
                                    'value'     => EmployeeStateEnum::WORKING->value,
                                    'options'   => [
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
                                    ]
                                ],
                                'employment_start_at' => [
                                    'type'     => 'date',
                                    'label'    => __('employment start at'),
                                    'value'    => '',
                                    'required' => true
                                ],
                            ]
                        ],
                        [
                            'title'  => __('job'),
                            'fields' => [
                                'job_title' => [
                                    'type'        => 'input',
                                    'label'       => __('job title'),
                                    'placeholder' => __('Job title'),
                                    'searchable'  => true,
                                    'value'       => ''
                                ],
                                'positions' => [
                                    'type'     => 'employeePosition',
                                    'required' => true,
                                    'label'    => __('position'),
                                    'options'  => [
                                        'positions'   => JobPositionResource::collection($this->organisation->jobPositions),
                                        'shops'       => ShopResource::collection($this->organisation->shops()->where('type', '!=', ShopTypeEnum::FULFILMENT)->get()),
                                        'fulfilments' => ShopResource::collection($this->organisation->shops()->where('type', '=', ShopTypeEnum::FULFILMENT)->get()),
                                        'warehouses'  => WarehouseResource::collection($this->organisation->warehouses),
                                    ],
                                    'value'    => new \stdClass(),
                                    'full'     => true
                                ],
                            ]
                        ],
                        [
                            'title'  => __('User credentials'),
                            'fields' => [

                                'username' => [
                                    'type'  => 'input',
                                    'label' => __('username'),
                                    'value' => ''

                                ],
                                'password' => [
                                    'type'  => 'password',
                                    'label' => __('password'),
                                    'value' => ''
                                ],
                            ]
                        ],

                    ],
                    'route'     => [
                        'name'       => 'grp.models.org.employee.store',
                        'parameters' => [
                            'organisation' => $organisation->id
                        ]
                    ]
                ],
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function asController(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $request);
    }


    public function getBreadcrumbs($routeParameters): array
    {
        return array_merge(
            IndexEmployees::make()->getBreadcrumbs('grp.org.hr.employees.index', $routeParameters),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating employee'),
                    ]
                ]
            ]
        );
    }

}
