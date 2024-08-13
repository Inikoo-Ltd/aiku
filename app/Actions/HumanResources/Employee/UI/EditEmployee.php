<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Mar 2023 19:14:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee\UI;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Inventory\Warehouse\WarehouseStateEnum;
use App\Enums\Manufacturing\Production\ProductionStateEnum;
use App\Http\Resources\HumanResources\JobPositionResource;
use App\Http\Resources\Inventory\WarehouseResource;
use App\Http\Resources\Catalogue\ShopResource;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditEmployee extends OrgAction
{
    protected Organisation $organisation;

    public function handle(Employee $employee): Employee
    {
        return $employee;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasPermissionTo("human-resources.{$this->organisation->id}.edit");
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
        $jobPositionsData = (object) $employee->jobPositions->map(function ($jobPosition) {
            $scopes = collect($jobPosition->pivot->scopes)->mapWithKeys(function ($scopeIds, $scope) use ($jobPosition) {
                return match ($scope) {
                    'Warehouse' => [
                        'warehouses' => $this->organisation->warehouses->whereIn('id', $scopeIds)->pluck('slug')->toArray()
                    ],
                    'Shop' => [
                        'shops' => $this->organisation->shops->whereIn('id', $scopeIds)->pluck('slug')->toArray()
                    ],
                    'Fulfilment' => [
                        'fulfilments' => $this->organisation->fulfilments->whereIn('id', $scopeIds)->pluck('slug')->toArray()
                    ],
                    default => []
                };
            });

            return [$jobPosition->code => $scopes->toArray()];
        })->reduce(function ($carry, $item) {
            return array_merge_recursive($carry, $item);
        }, []);

        $sections['properties'] = [
            'label'  => __('Properties'),
            'icon'   => 'fal fa-sliders-h',
            'fields' => [
                'worker_number'       => [
                    'type'     => 'input',
                    'label'    => __('worker number'),
                    'required' => true,
                    'value'    => $employee->worker_number
                ],
                'alias'               => [
                    'type'     => 'input',
                    'label'    => __('alias'),
                    'required' => true,
                    'value'    => $employee->alias
                ],
                'work_email'          => [
                    'type'  => 'input',
                    'label' => __('work email'),
                    'value' => $employee->work_email ?? ''
                ],
                'state'               => [
                    'type'    => 'employeeState',
                    'mode'    => 'card',
                    'label'   => 'Employee status',
                    'required'=> true,
                    'options' => [
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
                    'value'   => [
                        'state'                 => $employee->state,
                        'employment_start_at'   => $employee->employment_start_at ?? '',
                        'employment_end_at'     => $employee->employment_end_at   ?? '',
                    ]
                ],
                'job_title'           => [
                    'type'        => 'input',
                    'label'       => __('job title'),
                    'placeholder' => __('Job title'),
                    'searchable'  => true,
                    'value'       => $employee->job_title,
                    'required'    => true
                ],
                'positions' => [
                    'type'                   => 'employeePosition',
                    'required'               => true,
                    'label'                  => __('position'),
                    'list_authorised'        => [
                        'authorised_shops'       => 
                                            $this->organisation->shops()->where('state', '!=', ShopStateEnum::CLOSED)->count(),
                        'authorised_fulfilments' =>
                                            $this->organisation->shops()->where('type', ShopTypeEnum::FULFILMENT)->whereIn('state', [ShopStateEnum::IN_PROCESS, ShopStateEnum::OPEN, ShopStateEnum::CLOSING_DOWN])->count(),
                        'authorised_warehouses' =>
                                            $this->organisation->warehouses()->where('state', '!=', WarehouseStateEnum::CLOSED)->count(),
                        'authorised_productions' =>
                                            $this->organisation->productions()->where('state', '!=', ProductionStateEnum::CLOSED)->count(),
                    ],

                    'options'  => [
                        'positions'           => JobPositionResource::collection($this->organisation->jobPositions),
                        'shops'               => ShopResource::collection($this->organisation->shops()->where('type', '!=', ShopTypeEnum::FULFILMENT)->get()),
                        'fulfilments'         => ShopResource::collection($this->organisation->shops()->where('type', '=', ShopTypeEnum::FULFILMENT)->get()),
                        'warehouses'          => WarehouseResource::collection($this->organisation->warehouses),
                    ],
                    'value'    => $jobPositionsData,  // Should return an object
                    'full'     => true
                ],


            ]
        ];


        $sections['personal'] = [
            'label'  => __('Personal information'),
            'icon'   => 'fal fa-id-card',
            'fields' => [
                'contact_name'  => [
                    'type'        => 'input',
                    'label'       => __('name'),
                    'placeholder' => __('Name'),
                    'value'       => $employee->contact_name,
                    'required'    => true
                ],
                'date_of_birth' => [
                    'type'        => 'date',
                    'label'       => __('date of birth'),
                    'placeholder' => __('Date of birth'),
                    'value'       => $employee->date_of_birth
                ],
                'email'         => [
                    'type'  => 'input',
                    'label' => __('personal email'),
                    'value' => $employee->email
                ],
            ]
        ];

        $sections['credentials'] = [
            'label'  => __('Credentials'),
            'icon'   => 'fal fa-key',
            'fields' => [
                'username' => [
                    'type'  => 'input',
                    'label' => __('username'),
                    'value' => $employee->user ? $employee->user->username : ''

                ],
                'password' => [
                    'type'  => 'password',
                    'label' => __('password'),

                ],
            ]
        ];

        $sections['pin'] = [
            'label'  => __('Pin'),
            'icon'   => 'fal fa-key',
            'fields' => [
                'pin' => [
                    'type'  => 'input',
                    'label' => __('pin'),
                    'value' => $employee->pin
                ],
            ]
        ];

        $currentSection = 'properties';
        if ($request->has('section') and Arr::has($sections, $request->get('section'))) {
            $currentSection = $request->get('section');
        }


        return Inertia::render(
            'EditModel',
            [
                'live_users'=> [
                    'icon_left'   => [
                        'icon' => 'fal fa-user-hard-hat',
                        'class'=> 'text-lime-400'
                    ],
                    'icon_right'  => [
                        'icon' => 'fal fa-pencil',
                        'class'=> 'text-gray-300'
                    ],
                ],
                'title'       => __('employee'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'pageHead'    => [
                    'title'    => $employee->contact_name,
                    'actions'  => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],

                'formData'    => [
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

    public function getBreadcrumbs(array $routeParameters): array
    {
        return ShowEmployee::make()->getBreadcrumbs(routeParameters:$routeParameters, suffix: '('.__('Editing').')');
    }
}
