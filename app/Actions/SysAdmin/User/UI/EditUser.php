<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\UI;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\User\GetUserOrganisationScopeJobPositionsData;
use App\Actions\SysAdmin\User\GetUserGroupScopeJobPositionsData;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Catalogue\ShopResource;
use App\Http\Resources\HumanResources\JobPositionResource;
use App\Http\Resources\Inventory\WarehouseResource;
use App\Http\Resources\SysAdmin\Organisation\OrganisationsResource;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditUser extends OrgAction
{
    public function handle(User $user): User
    {
        return $user;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("sysadmin.view");
    }

    public function asController(User $user, ActionRequest $request): User
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($user);
    }


    public function htmlResponse(User $user, ActionRequest $request): Response
    {

        $jobPositionsOrganisationsData = [];
        foreach ($this->group->organisations as $organisation) {
            $jobPositionsOrganisationData                       = GetUserOrganisationScopeJobPositionsData::run($user, $organisation);
            $jobPositionsOrganisationsData[$organisation->slug] = $jobPositionsOrganisationData;
        }



        $permissionsGroupData = GetUserGroupScopeJobPositionsData::run($user);
        $organisations = $user->group->organisations;
        $orgIds = $user->getOrganisations()->pluck('id')->toArray();

        $reviewData    = $organisations->mapWithKeys(function ($organisation) use ($user, $orgIds) {
            return [
                $organisation->slug => [
                    'is_employee' => in_array($organisation->id, $orgIds),
                    'number_job_positions' => $organisation->humanResourcesStats->number_job_positions,
                    'job_positions'        => $organisation->jobPositions->mapWithKeys(function ($jobPosition) {
                        return [
                            $jobPosition->slug => [
                                'job_position' => $jobPosition->name,
                                'number_roles' => $jobPosition->stats->number_roles
                            ]
                        ];
                    })
                ]
            ];
        })->toArray();

        $organisationList = OrganisationsResource::collection($organisations);

        /** @var Employee $employee */
        $employee = $user->employees()->first();


        return Inertia::render("EditModel", [
            "title"       => __("user"),
            "breadcrumbs" => $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            "pageHead"    => [
                "title"   => $user->username,
                "icon"    => 'fal fa-user-circle',
                "model"   => __('user'),
                "actions" => [
                    [
                        "type"  => "button",
                        "style" => "exitEdit",
                        "route" => [
                            "name"       => preg_replace('/edit$/', "show", $request->route()->getName()),
                            "parameters" => array_values($request->route()->originalParameters()),
                        ],
                    ],
                ],
            ],


            "formData" => [
                "blueprint" => [
                    [
                        "label"   => __("Access"),
                        "title"   => __("access"),
                        "icon"    => "fal fa-door-closed",
                        "current" => true,
                        "fields"  => [
                            "status" => [
                                "type"        => "toggle",
                                "label"       => __("can login"),
                                "value"       => $user->status,
                            ],
                        ],
                    ],
                    [
                        "label"   => __("Credentials"),
                        "title"   => __("id"),
                        "icon"    => "fal fa-key",
                        "current" => false,
                        "fields"  => [
                            "username" => [
                                "type"        => "input",
                                "label"       => __("username"),
                                "placeholder" => "username",
                                "value"       => $user->username ?? '',
                            ],
                            "email"    => [
                                "type"        => "input",
                                "label"       => __("email"),
                                "placeholder" => __("example@mail.com"),
                                "value"       => $user->email ?? '',
                            ],
                            "password" => [
                                "type"        => "password",
                                "label"       => __("password"),
                                "placeholder" => "********",
                                "value"       => '',
                            ],
                        ],
                    ],
                    "permissions" => [
                        "label"   => __("Permissions"),
                        "title"   => __("Permissions"),
                        "icon"    => "fa-light fa-user-lock",
                        "current" => false,
                        "fields"  => [
                            "permissions" => [
                                "full"              => true,
                                "noSaveButton"      => true,
                                "type"              => "permissions",
                                "review"            => $reviewData,
                                'organisation_list' => $organisationList,
                                "current_organisation"  => $user->getOrganisation(),
                                'updatePseudoJobPositionsRoute'       => [
                                    'method'     => 'patch',
                                    "name"       => "grp.models.user.group_permissions.update",
                                    'parameters' => [
                                        'user' => $user->id
                                    ]
                                ],
                                'updateJobPositionsRoute'       => [
                                    'method'     => 'patch',
                                    "name"       => "grp.models.user.organisation_pseudo_job_positions.update",
                                    'parameters' => [
                                        'user' => $user->id,
                                        'organisation' => null // fill in the organisation id in the frontend
                                    ]
                                ],
                                'updateEmployeeJobPositionsRoute'       => [
                                    'method'     => 'patch',
                                    "name"       => "grp.models.employee.update",
                                    //todo support case user has employed in 2 organisations
                                    'parameters' => [
                                        'employee' => $employee ? $employee->id : null
                                    ]
                                ],


                                'options'           => Organisation::get()->flatMap(function (Organisation $organisation) {
                                    return [
                                        $organisation->slug => [
                                            'positions'   => JobPositionResource::collection($organisation->jobPositions),
                                            'shops'       => ShopResource::collection($organisation->shops()->where('type', '!=', ShopTypeEnum::FULFILMENT)->get()),
                                            'fulfilments' => ShopResource::collection($organisation->shops()->where('type', '=', ShopTypeEnum::FULFILMENT)->get()),
                                            'warehouses'  => WarehouseResource::collection($organisation->warehouses),
                                        ]
                                    ];
                                })->toArray(),
                                'value'             => [
                                    'group'         => $permissionsGroupData,
                                    'organisations' => $jobPositionsOrganisationsData,
                                ],

                                "fullComponentArea" => true,
                            ],
                        ],
                    ],


                ],
                "args"      => [
                    "updateRoute" => [
                        "name"       => "grp.models.user.update",
                        "parameters" => [$user->id],
                    ],
                ],
            ],
        ]);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowUser::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', "show", $routeName),
            routeParameters: $routeParameters,
            suffix: "(".__("editing").")"
        );
    }
}
