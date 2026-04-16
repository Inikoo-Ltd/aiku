<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Guest\UI;

use App\Actions\GrpAction;
use App\Actions\SysAdmin\User\UI\Traits\HasPermissionsForm;
use App\Models\SysAdmin\Guest;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditGuest extends GrpAction
{
    use HasPermissionsForm;

    public function handle(Guest $guest): Guest
    {
        return $guest;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo('sysadmin.edit');

        return $request->user()->authTo("sysadmin.view");
    }

    public function asController(Guest $guest, ActionRequest $request): Guest
    {
        $group = group();
        $this->initialisation($group, $request);

        return $this->handle($guest);
    }


    public function htmlResponse(Guest $guest, ActionRequest $request): Response
    {
        $user            = $guest->getUser();
        $permissionsData = $this->getPermissionsFormData($user);

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Editing guest').' '.$guest->contact_name,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'   => $guest->contact_name,
                    'actions' => [
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
                            "label"  => __("Personal Information"),
                            'icon'   => 'fal fa-id-card',
                            'title'  => __('personal information'),
                            'fields' => [

                                'contact_name' => [
                                    'type'  => 'input',
                                    'label' => __('Name'),
                                    'value' => $guest->contact_name
                                ],
                                'email'        => [
                                    'type'  => 'input',
                                    'label' => __('Email'),
                                    'value' => $guest->email
                                ],
                                'phone'        => [
                                    'type'  => 'phone',
                                    'label' => __('phone'),
                                    'value' => $guest->phone
                                ],

                            ]
                        ],
                        [
                            "label"  => __("Access"),
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
                            "label"  => __("Credentials"),
                            'title'  => __('Credentials'),
                            'icon'   => 'fal fa-key',
                            'fields' => [
                                'username' => [
                                    'type'  => 'input',
                                    'label' => __('Username'),
                                    'value' => $user->username

                                ],
                                'password' => [
                                    'type'        => 'password',
                                    "placeholder" => "********",
                                    'label'       => __('Password'),

                                ],
                            ]
                        ],
                        "permissions" => [
                            "label"   => __("Permissions"),
                            "title"   => __("Permissions"),
                            "icon"    => "fa-light fa-user-lock",
                            "current" => false,
                            "fields"  => [
                                "permissions" => $this->getPermissionsFieldDefinition($user, $permissionsData),
                            ],
                        ],

                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.guest.update',
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
