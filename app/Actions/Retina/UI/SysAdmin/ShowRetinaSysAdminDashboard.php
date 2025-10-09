<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Feb 2024 20:59:47 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\SysAdmin;

use App\Actions\Retina\SysAdmin\GetRetinaCustomerAddressManagement;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use Inertia\Inertia;
use Inertia\Response;
use App\Http\Resources\CRM\CustomersResource;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaSysAdminDashboard extends RetinaAction
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
        $title = __('Manage Account');

        return Inertia::render(
            'SysAdmin/RetinaSysAdminDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-users-cog'],
                        'title' => $title
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('user'),
                            'route' => [
                                'name'       => 'retina.sysadmin.web-users.create',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ]
                    ]

                ],
                'users' => [
                    'active' => $this->customer->stats->number_current_web_users,
                    'inactive' => $this->customer->stats->number_web_users - $this->customer->stats->number_current_web_users
                ],
                'customer'     => CustomersResource::make($this->customer),
                'status'              => $this->customer->status,
                'additional_data'     => $this->customer->data,
                'currency_code'  => $this->shop->currency->code,
                'address_management' => GetRetinaCustomerAddressManagement::run(customer:$this->customer),
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
                                'name' => 'retina.sysadmin.fulfilment.dashboard'
                            ],
                            'label' => __('Account'),
                        ]
                    ]
                ]
            );
    }
}
