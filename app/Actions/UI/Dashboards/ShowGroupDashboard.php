<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Dec 2024 00:41:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dashboards;

use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithDashboardSettings;
use App\Actions\Traits\WithDashboard;
use App\Enums\Dashboards\GroupDashboardIntervalTabsEnum;
use App\Enums\Dashboards\GroupDashboardSalesTableTabsEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowGroupDashboard extends OrgAction
{
    use WithDashboard;

    // <-- to delete

    use WithDashboardSettings;
    use WithDashboardIntervalOption;

    public function handle(Group $group, ActionRequest $request): Response
    {
        $userSettings = $request->user()->settings;
        $settings     = Arr::get($request->user()->settings, 'ui.state.organisation_dashboard', []);

        $currentTab = Arr::get($userSettings, 'group_dashboard_tab', Arr::first(GroupDashboardSalesTableTabsEnum::values()));
        if (!in_array($currentTab, GroupDashboardSalesTableTabsEnum::values())) {
            $currentTab = Arr::first(GroupDashboardSalesTableTabsEnum::values());
        }


        $dashboard = [
            'super_blocks' => [
                [
                    'id'        => 'group_dashboard_tab',
                    'intervals' => [
                        'options' => $this->dashboardIntervalOption(),
                        'value'   => Arr::get($userSettings, 'selected_interval', 'all')  // fix this
                    ],
                    'settings'  => [
                        'model_state'       => $this->dashboardShopStateTypeSettings($settings, 'left'),
                        'data_display_type' => $this->dashboardDataDisplayTypeSettings($settings),
                    ],
                    'blocks'    => [
                        [
                            'id'          => 'sales_table',
                            'type'        => 'table',
                            'current_tab' => $currentTab,
                            'tabs'        => GroupDashboardSalesTableTabsEnum::navigation(),
                            'tables'      => GroupDashboardSalesTableTabsEnum::tables($group),
                            'charts'      => [] // <-- to do (refactor) need to call OrganisationDashboardSalesChartsEnum

                        ]
                    ]

                ]

            ]
        ];

        return Inertia::render(
            'Dashboard/GrpDashboard',
            [
                'title'       => __('Dashboard Group'),
                'breadcrumbs' => $this->getBreadcrumbs(__('Dashboard')),
                'dashboard'   => $dashboard
            ]
        );
    }

    public function asController(ActionRequest $request): Response
    {
        $group = group();
        $this->initialisationFromGroup($group, $request)->withTabDashboardInterval(GroupDashboardIntervalTabsEnum::values());

        return $this->handle($group, $request);
    }

    public function getBreadcrumbs($label = null): array
    {
        return [
            [

                'type'   => 'simple',
                'simple' => [
                    'icon'  => 'fal fa-tachometer-alt-fast',
                    'label' => $label,
                    'route' => [
                        'name' => 'grp.dashboard.show'
                    ]
                ]

            ],

        ];
    }
}
