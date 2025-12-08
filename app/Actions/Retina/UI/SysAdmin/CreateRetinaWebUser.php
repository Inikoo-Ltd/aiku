<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-10h-41m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\UI\SysAdmin;

use App\Actions\RetinaAction;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateRetinaWebUser extends RetinaAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title' => __('Create User'),
                'pageHead' => [
                    'title' => __('Create User'),
                    'icon' => [
                        'icon' => 'fal fa-user-circle',
                        'title' => __('user'),
                    ],
                    'actions' => [
                        [
                            'type' => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name' => 'retina.sysadmin.web-users.index',
                                'parameters' => $request->route()->originalParameters(),
                            ],
                        ],
                    ],
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'fields' => [
                                'contact_name' => [
                                    'type' => 'input',
                                    'label' => __('contact name'),
                                    'value' => '',
                                ],
                                'email' => [
                                    'required' => true,
                                    'type' => 'input',
                                    'label' => __('email'),
                                    'value' => $this->customer->hasUsers() ? '' : $this->customer->email,
                                ],
                                'username' => [
                                    'required' => true,
                                    'type' => 'input',
                                    'label' => __('username'),
                                    'value' => '',
                                ],
                                'password' => [
                                    'required' => true,
                                    'type' => 'password',
                                    'label' => __('password'),
                                    'value' => '',
                                ],

                            ],
                        ],
                    ],
                    'route' => [
                        'name' => 'retina.models.web-users.store',
                        'parameters' => [],
                    ],
                ],
            ]
        );
    }

    public function asController(ActionRequest $request): ActionRequest
    {
        $this->initialisation($request);

        return $request;
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                IndexRetinaWebUsers::make()->getBreadcrumbs('retina.sysadmin.web-users.index'),
                [
                    [
                        'type' => 'creatingModel',
                        'creatingModel' => [
                            'label' => __('Creating web user'),
                        ],
                    ],
                ]
            );
    }
}
