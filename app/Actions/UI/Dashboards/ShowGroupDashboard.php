<?php

namespace App\Actions\UI\Dashboards;

use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\Settings\WithDashboardCurrencyTypeSettings;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithDashboardSettings;
use App\Actions\Traits\Dashboards\WithLatestStockHistory;
use App\Actions\Traits\Dashboards\WithPerformanceDateResolution;
use App\Actions\Traits\WithDashboard;
use App\Actions\Traits\WithTabsBox;
use App\Enums\Dashboards\GroupDashboardSalesTableTabsEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowGroupDashboard extends OrgAction
{
    use WithDashboard;
    use WithDashboardSettings;
    use WithDashboardIntervalOption;
    use WithDashboardCurrencyTypeSettings;
    use WithLatestStockHistory;
    use WithTabsBox;
    use WithPerformanceDateResolution;

    public function handle(Group $group, ActionRequest $request): Response
    {
        $userSettings = $request->user()->settings;

        $tabValues = GroupDashboardSalesTableTabsEnum::values();
        $defaultTab = Arr::first($tabValues);
        $currentTab = Arr::get($userSettings, 'group_dashboard_tab', $defaultTab);

        $currentTabEnum = GroupDashboardSalesTableTabsEnum::tryFrom($currentTab) ?? GroupDashboardSalesTableTabsEnum::from($defaultTab);
        $currentTab = $currentTabEnum->value;

        $saved_interval = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;
        $performanceDates = $this->resolvePerformanceDates($saved_interval, $userSettings);

        $timeSeriesData = GetGroupDashboardTimeSeriesData::run($group, $performanceDates[0], $performanceDates[1]);
        $tabNavigation = GroupDashboardSalesTableTabsEnum::navigation();
        $primaryTables = GroupDashboardSalesTableTabsEnum::tablesForTabs($group, $timeSeriesData, [$currentTabEnum]);
        $secondaryTables = GroupDashboardSalesTableTabsEnum::tablesForTabs($group, $timeSeriesData, [$currentTabEnum], true);

        $tabsBox = $this->getTabsBox($group);

        $dashboard = [
            'super_blocks' => [
                [
                    'id'        => 'group_dashboard_tab',
                    'intervals' => [
                        'options'        => $this->dashboardIntervalOption(),
                        'value'          => $saved_interval,
                        'range_interval' => DashboardIntervalFilters::run($saved_interval, $userSettings)
                    ],
                    'settings'  => [
                        'model_state_type'  => $this->dashboardModelStateTypeSettings($userSettings, 'left'),
                        'data_display_type' => $this->dashboardDataDisplayTypeSettings($userSettings),
                        'currency_type'     => $this->dashboardCurrencyTypeSettings($group, $userSettings),
                    ],
                    'blocks'    => [
                        [
                            'id'          => 'sales_table',
                            'type'        => 'table',
                            'current_tab' => $currentTab,
                            'tabs'        => $tabNavigation,
                            'tables'      => $primaryTables,
                            'tab_fetch_route' => [
                                'name' => 'grp.dashboard.tab-data',
                            ],
                            'charts'      => [],
                        ]
                    ],
                    'blocks_2'    => [
                        [
                            'id'          => 'sales_table_2',
                            'type'        => 'table',
                            'tabs'        => $tabNavigation,
                            'tables'      => $secondaryTables,
                        ]
                    ],
                    'tabs_box'    => [
                        'current'    => $this->tab,
                        'navigation' => $tabsBox
                    ],
                ]
            ],
        ];

        return Inertia::render(
            'Dashboard/GrpDashboard',
            [
                'title'              => __('Dashboard Group'),
                'breadcrumbs'        => $this->getBreadcrumbs(__('Dashboard')),
                'dashboard'          => $dashboard,
                'stockHistoryGroup'  => $this->getGroupStockHistoryData($group),
            ]
        );
    }

    public function asController(ActionRequest $request): Response
    {
        $group = group();

        $this->initialisationFromGroup($group, $request);

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
