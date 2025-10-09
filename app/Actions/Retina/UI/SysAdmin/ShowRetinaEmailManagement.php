<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Feb 2024 23:54:37 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\SysAdmin;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\CRM\CustomerResource;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaEmailManagement extends RetinaAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }


    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($request);
    }


    public function handle(ActionRequest $request): Response
    {

        $customer = $request->user()->customer;
        $spain = \App\Models\Helpers\Country::where('code', 'ES')->first();



        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Email management'),
                'pageHead'    => [
                    'title' => __('Email management'),
                ],
                "formData" => [
                    "blueprint" =>
                    [
                        [
                            'title'  => __('Marketing information'),
                            'label'  => __('marketing'),
                            'icon'    => 'fa-light fa-envelope',
                            'fields' => [
                                    'email_subscriptions' => [
                                        'type'  => 'email_subscriptions',
                                        'full' => true,
                                        'noSaveButton' => true,
                                        "updateRoute" => [
                                            "name"       => "retina.models.customer.comms.update",
                                            'parameters' => [$customer->comms->id]
                                        ],
                                        'customer' => CustomerResource::make($customer)->getArray(),
                                    ],
                                ]
                        ]
                    ],
                    "args"      => [
                        "updateRoute" => [
                            "name"       => "retina.models.customer.comms.update",
                            'parameters' => [$customer->comms->id]
                        ],
                    ],
                ],
            ]
        );
    }



    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.sysadmin.settings.edit'
                            ],
                            'label'  => __('Account management'),
                        ]
                    ]
                ]
            );
    }
}
