<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 05 Jan 2025 14:59:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\UI\SysAdmin\SysAdminAnalyticsDashboardTabsEnum;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSysAdminAnalyticsDashboard extends OrgAction
{
    use WithAnalyticsSubNavigations;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('sysadmin.view');
    }

    public function handle(Group $group): Group
    {
        return $group;
    }

    public function asController(ActionRequest $request): Group
    {
        $group = group();
        $this->initialisationFromGroup($group, $request)->withTab(SysAdminAnalyticsDashboardTabsEnum::values());

        return $this->handle($group);
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $subNavigation = $this->getAnalyticsNavigation($this->group, $request);

        return Inertia::render(
            'SysAdmin/SysAdminAnalyticsDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title' => __('System analytics'),
                'pageHead' => [
                    'icon' => [
                        'icon' => ['fal', 'fa-analytics'],
                        'title' => __('System analytics'),
                    ],
                    'title' => __('System analytics'),
                    'subNavigation' => $subNavigation,
                ],
                // 'tabs' => [
                //     'current'    => $this->tab,
                //     'navigation' => SysAdminAnalyticsDashboardTabsEnum::navigation()
                // ],
            ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.sysadmin.analytics.dashboard',
                            ],
                            'label' => __('analytics'),
                        ],
                    ],
                ]
            );
    }
}
