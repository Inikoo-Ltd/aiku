<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 05 Jan 2025 12:39:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\UI;

use App\Actions\OrgAction;
use App\Actions\Search\GetSearchAnalytics;
use App\Actions\SysAdmin\GetMcpAnalytics;
use App\Actions\SysAdmin\GetUsersInsights;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSysAdminDashboard extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("sysadmin.view");
    }


    public function handle(Group $group): Group
    {
        return $group;
    }

    public function asController(ActionRequest $request): Group
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($group);
    }

    public function htmlResponse(Group $group): Response
    {
        return Inertia::render(
            'SysAdmin/SysAdminDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('system Administration'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-users-cog'],
                        'title' => __('System Administration')
                    ],
                    'title' => __('System Administration'),
                ],
                'users_insights'  => GetUsersInsights::run($group),
                'search_insights' => GetSearchAnalytics::run($group),
                'ai_insights'     => GetMcpAnalytics::run($group),
            ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.sysadmin.dashboard'
                        ],
                        'label' => __('System Administration'),
                    ]
                ]
            ]
        );
    }
}
