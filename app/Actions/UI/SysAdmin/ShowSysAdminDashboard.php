<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:48:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\SysAdmin;

use App\Actions\UI\Grp\Dashboard\ShowDashboard;
use App\Actions\UI\WithInertia;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowSysAdminDashboard
{
    use AsAction;
    use WithInertia;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasPermissionTo("sysadmin.view");
    }


    public function asController(): bool
    {
        return true;
    }


    public function htmlResponse(): Response
    {
        $group=app('group');

        return Inertia::render(
            'SysAdmin/SysAdminDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('system administration'),
                'pageHead'    => [
                    'title' => __('system administration'),
                ],
                'stats' => [
                    [
                        'name' => __('users'),
                        'stat' => $group->sysadminStats->number_users_status_active,
                        'href' => ['name'=>'grp.sysadmin.users.index']
                    ],
                    [
                        'name' => __('guests'),
                        'stat' => $group->sysadminStats->number_guests_status_active,
                        'href' => ['name'=>'grp.sysadmin.guests.index']
                    ]
                ]

            ]
        );
    }



    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.sysadmin.dashboard'
                            ],
                            'label'  => __('system administration'),
                        ]
                    ]
                ]
            );
    }
}
