<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Apr 2025 13:06:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\UI;

use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Actions\Traits\Dashboards\Settings\WithDashboardCurrencyTypeSettings;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithDashboardSettings;
use App\Actions\Traits\WithDashboard;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Dashboards\MastersDashboardSalesTableTabsEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMastersDashboard extends OrgAction
{
    use WithMastersAuthorisation;
    use WithDashboard;
    use WithDashboardSettings;
    use WithDashboardIntervalOption;
    use WithDashboardCurrencyTypeSettings;

    public function handle(Group $group): Group
    {
        return $group;
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }


    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $userSettings = $request->user()->settings;

        $currentTab = Arr::get($userSettings, 'masters_dashboard_tab', Arr::first(MastersDashboardSalesTableTabsEnum::values()));
        if (!in_array($currentTab, MastersDashboardSalesTableTabsEnum::values())) {
            $currentTab = Arr::first(MastersDashboardSalesTableTabsEnum::values());
        }

        $saved_interval = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;


        $dashboard = [
            'super_blocks' => [
                [
                    'id'        => 'masters_dashboard_tab',
                    'intervals' => [
                        'options'        => $this->dashboardIntervalOption(),
                        'value'          => Arr::get($userSettings, 'selected_interval', 'all'),  // fix this
                        'range_interval' => DashboardIntervalFilters::run($saved_interval)
                    ],
                    'settings'  => [
                        'model_state_type'  => $this->dashboardModelStateTypeSettings($userSettings, 'left'),
                        'data_display_type' => $this->dashboardDataDisplayTypeSettings($userSettings),
                        'currency_type'     => [
                            'display' => false,
                            'id'      => 'scope_masters_currency_type',
                            'align'   => 'right',
                            'type'    => 'radio',
                            'value'   => 'grp',
                            'options' => []
                        ],
                    ],
                    'blocks'    => [
                        [
                            'id'          => 'sales_table',
                            'type'        => 'table',
                            'current_tab' => $currentTab,
                            'tabs'        => MastersDashboardSalesTableTabsEnum::navigation(),
                            'tables'      => MastersDashboardSalesTableTabsEnum::tables($group),
                            'charts'      => [] // <-- to do (refactor), need to call OrganisationDashboardSalesChartsEnum

                        ]
                    ]

                ]

            ]
        ];


        return Inertia::render(
            'Masters/MastersDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('masters'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-ruler-combined'],
                        'title' => __('masters')
                    ],
                    'title' => __('master catalogue'),
                ],
                'dashboard'   => $dashboard


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
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.masters.dashboard'
                            ],
                            'label' => __('Masters'),
                        ]
                    ]
                ]
            );
    }


}
