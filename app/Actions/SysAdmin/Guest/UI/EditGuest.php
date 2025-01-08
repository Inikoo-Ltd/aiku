<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Guest\UI;

use App\Actions\GrpAction;
use App\Actions\HumanResources\Employee\UI\GetJobPositionsOrganisationData;
use App\Actions\HumanResources\Employee\UI\GetPermissionGroupData;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Api\Dropshipping\ShopResource;
use App\Http\Resources\HumanResources\JobPositionResource;
use App\Http\Resources\Inventory\WarehouseResource;
use App\Http\Resources\SysAdmin\Organisation\OrganisationsResource;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditGuest extends GrpAction
{
    public function handle(Guest $guest): Guest
    {
        return $guest;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->hasPermissionTo('sysadmin.edit');
        return $request->user()->hasPermissionTo("sysadmin.view");
    }

    public function asController(Guest $guest, ActionRequest $request): Guest
    {
        $group = group();
        $this->initialisation($group, $request);

        return $this->handle($guest);
    }



    public function htmlResponse(Guest $guest, ActionRequest $request): Response
    {
        $user = $guest->getUser();
        $organisations = $user->group->organisations;
        $reviewData    = $organisations->mapWithKeys(function ($organisation) {
            return [
                $organisation->slug => [
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
        $permissionsGroupData = GetPermissionGroupData::run($user, $this->group);
        $jobPositionsOrganisationsData = [];
        foreach ($this->group->organisations as $organisation) {
            $jobPositionsOrganisationData = GetJobPositionsOrganisationData::run($user, $organisation);
            $jobPositionsOrganisationsData[] = $jobPositionsOrganisationData;
        }

        $organisationList = OrganisationsResource::collection($organisations);

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('guest'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'     => $guest->contact_name,
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            "label"   => __("Personal Information"),
                            'icon'   => 'fal fa-id-card',
                            'title'  => __('personal information'),
                            'fields' => [

                                'contact_name' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $guest->contact_name
                                ],
                                'email' => [
                                    'type'  => 'input',
                                    'label' => __('email'),
                                    'value' => $guest->email
                                ],
                                'phone' => [
                                    'type'  => 'phone',
                                    'label' => __('phone'),
                                    'value' => $guest->phone
                                ],

                            ]
                        ],
                        [
                            "label"   => __("Access"),
                            'title'  => __('access'),
                            'icon'   => 'fal fa-chess-clock',
                            'fields' => [

                                'status' => [
                                        'type'     => 'toggle',
                                        'label'    => __('status'),
                                        'value'    => $guest->status,
                                        'required' => true,
                                    ],
                            ]
                        ],
                        [
                            "label"   => __("Credentials"),
                            'title'  => __('credentials'),
                            'icon'   => 'fal fa-key',
                            'fields' => [
                                'username' => [
                                    'type'  => 'input',
                                    'label' => __('username'),
                                    'value' => $user ? $user->username : ''

                                ],
                                'password' => [
                                    'type'  => 'password',
                                    "placeholder" => "********",
                                    'label' => __('password'),

                                ],
                            ]
                        ],
                        "permissions_shop_organisation" => [
                            "label"   => __(" Permissions"),
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
                                    'updateRoute'       => [
                                        'method'     => 'patch',
                                        "name"       => "grp.models.user.permissions.update",
                                        'parameters' => [
                                            'user' => $user->id
                                        ]
                                    ],


                                    'options' => Organisation::get()->flatMap(function (Organisation $organisation) {
                                        return [
                                            $organisation->slug => [
                                                'positions'   => JobPositionResource::collection($organisation->jobPositions),
                                                'shops'       => ShopResource::collection($organisation->shops()->where('type', '!=', ShopTypeEnum::FULFILMENT)->get()),
                                                'fulfilments' => ShopResource::collection($organisation->shops()->where('type', '=', ShopTypeEnum::FULFILMENT)->get()),
                                                'warehouses'  => WarehouseResource::collection($organisation->warehouses),
                                            ]
                                        ];
                                    })->toArray(),
                                    'value'   => [
                                        'group' => $permissionsGroupData,
                                        'organisations' =>  $jobPositionsOrganisationsData,
                                    ],

    //                                "value"             => $user->pseudoJobPositions->flatMap(function (JobPosition $jobPosition) {
    //                                    return [
    //                                        $jobPosition->organisation->slug => [
    //                                            $jobPosition->code => match (array_key_first($jobPosition->pivot->scopes)) {
    //                                                class_basename(Shop::class) => [
    //                                                    'shops' => $jobPosition->organisation->shops->whereIn('id', Arr::get($jobPosition->pivot->scopes, class_basename(Shop::class)))->pluck('slug')
    //                                                ],
    //                                                default => null
    //                                            }
    //                                        ]
    //                                    ];
    //                                }),
                                    "fullComponentArea" => true,
                                ],
                            ],
                        ],

                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'      => 'grp.models.guest.update',
                            'parameters' => $guest->id

                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowGuest::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
