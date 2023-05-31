<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 May 2023 13:03:25 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Auth\Guest\UI;

use App\Actions\InertiaAction;
use App\Enums\Auth\GuestTypeEnum;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateGuest extends InertiaAction
{
    /**
     * @throws \Exception
     */
    public function handle(ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->parameters
                ),
                'title'       => __('new guest'),
                'pageHead'    => [
                    'title'        => __('new guest'),
                    'cancelCreate' => [
                        'route' => [
                            'name'       => 'sysadmin.guests.index',
                            'parameters' => array_values($this->originalParameters)
                        ],
                    ]

                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('personal information'),
                            'fields' => [
                                'name' => [
                                    'type'   => 'input',
                                    'label'  => __('name'),
                                    'value'  => '',
                                    'options'=> [
                                        'counter'=> true
                                    ]
                                ],
                                'email' => [
                                    'type'  => 'input',
                                    'label' => __('email'),
                                    'value' => ''
                                ],
                            ]
                        ],
                        [
                            'title'  => __('type'),
                            'fields' => [
                                'type' => [
                                    'type'         => 'select',
                                    'label'        => __('type'),
                                    'value'        => '',
                                    'placeholder'  => 'Select a Type',
                                    'options'      => Options::forEnum(GuestTypeEnum::class)
                                ],
                            ]
                        ]
                    ],
                    'route'      => [
                        'name'       => 'models.guest.store',

                    ]

                ],


            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->can('sysadmin.users.edit');
    }


    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($request);
    }

    public function getBreadcrumbs($suffix = null): array
    {
        return array_merge(
            IndexGuest::make()->getBreadcrumbs(
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('creating user'),
                    ]
                ]
            ]
        );
    }
}
