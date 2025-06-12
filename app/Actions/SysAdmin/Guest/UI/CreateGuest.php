<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Guest\UI;

use App\Actions\InertiaAction;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateGuest extends InertiaAction
{
    /**
     * @throws \Exception
     */
    public function handle(): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('new guest'),
                'pageHead'    => [
                    'title'   => __('new guest'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => 'grp.sysadmin.guests.index',
                                'parameters' => []
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title' => __('Credentials'),

                            'fields' => [
                                'username' => [
                                    'type'     => 'input',
                                    'label'    => __('username'),
                                    'required' => true
                                ],
                                'password' => [
                                    'type'     => 'password',
                                    'label'    => __('password'),
                                    'required' => true
                                ],

                            ],

                        ],
                        [
                            'title'  => __('personal information'),
                            'fields' => [
                                'company_name' => [
                                    'type'  => 'input',
                                    'label' => __('company'),
                                    'value' => '',
                                ],
                                'contact_name' => [
                                    'type'     => 'input',
                                    'label'    => __('name'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'phone'        => [
                                    'type'  => 'phone',
                                    'label' => __('phone'),
                                    'value' => ''
                                ],
                                'email'        => [
                                    'type'  => 'input',
                                    'label' => __('email'),
                                    'value' => ''
                                ],
                            ]
                        ],

                    ],
                    'route'     => [
                        'name' => 'grp.models.guest.store',

                    ]

                ],


            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('sysadmin.users.edit');
    }


    /**
     * @throws \Exception
     */
    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle();
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            IndexGuests::make()->getBreadcrumbs('grp.sysadmin.guests.index'),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating guest'),
                    ]
                ]
            ]
        );
    }
}
