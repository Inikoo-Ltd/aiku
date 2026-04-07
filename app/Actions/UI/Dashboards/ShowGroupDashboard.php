<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Dec 2024 00:41:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dashboards;

use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\Settings\WithDashboardCurrencyTypeSettings;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithDashboardSettings;
use App\Actions\Traits\WithDashboard;
use App\Actions\Traits\WithTabsBox;
use App\Enums\Dashboards\GroupDashboardSalesTableTabsEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Inventory\GroupStockHistory;
use App\Models\Inventory\OrganisationStockHistory;
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
    use WithTabsBox;

    public function handle(Group $group, ActionRequest $request): Response
    {
        $userSettings = $request->user()->settings;

        $currentTab = Arr::get($userSettings, 'group_dashboard_tab', Arr::first(GroupDashboardSalesTableTabsEnum::values()));

        if (!in_array($currentTab, GroupDashboardSalesTableTabsEnum::values())) {
            $currentTab = Arr::first(GroupDashboardSalesTableTabsEnum::values());
        }

        $saved_interval = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;

        $performanceDates = [null, null];
        if ($saved_interval === DateIntervalEnum::CUSTOM) {
            $rangeInterval = Arr::get($userSettings, 'range_interval', '');
            if ($rangeInterval) {
                $dates = explode('-', $rangeInterval);
                if (count($dates) === 2) {
                    $performanceDates = [$dates[0], $dates[1]];
                }
            }
        } elseif ($saved_interval !== DateIntervalEnum::ALL) {
            $intervalString = DashboardIntervalFilters::run($saved_interval);
            if ($intervalString) {
                $dates = explode('-', $intervalString);
                if (count($dates) === 2) {
                    $performanceDates = [$dates[0], $dates[1]];
                }
            }
        }

        $timeSeriesData = GetGroupDashboardTimeSeriesData::run($group, $performanceDates[0], $performanceDates[1]);

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
                            'tabs'        => GroupDashboardSalesTableTabsEnum::navigation(),
                            'tables'      => GroupDashboardSalesTableTabsEnum::tables($group, $timeSeriesData),
                            'charts'      => [],
                        ]
                    ],
                    'blocks_2'    => [
                        [
                            'id'          => 'sales_table_2',
                            'type'        => 'table',
                            'tabs'        => GroupDashboardSalesTableTabsEnum::navigation(),
                            'tables'      => GroupDashboardSalesTableTabsEnum::tables($group, $timeSeriesData, true),
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
                'stockHistoryGroup'  => app()->environment('local') ? $this->getGroupStockHistory($group) : null,
            ]
        );
    }

    public function asController(ActionRequest $request): Response
    {
        $group = group();

        $this->initialisationFromGroup($group, $request);

        return $this->handle($group, $request);
    }

    private function getGroupStockHistory(Group $group): ?array
    {
        $groupHistory = GroupStockHistory::query()
            ->where('group_id', $group->id)
            ->where('is_week', false)
            ->where('is_month', false)
            ->where('is_year', false)
            ->latest('date')
            ->first();

        if (!$groupHistory) {
            return null;
        }

        $group->loadMissing(['organisations' => fn ($q) => $q->where('type', OrganisationTypeEnum::SHOP)->with(['currency'])]);
        $ecommerceOrgs = $group->organisations->where('type', OrganisationTypeEnum::SHOP)->values();

        $orgHistories = collect();
        foreach ($ecommerceOrgs as $org) {
            $history = OrganisationStockHistory::query()
                ->where('organisation_id', $org->id)
                ->where('is_week', false)
                ->where('is_month', false)
                ->where('is_year', false)
                ->latest('date')
                ->first();
            if ($history) {
                $orgHistories->push(['org' => $org, 'history' => $history]);
            }
        }

        $totalSkus       = $groupHistory->number_org_stocks;
        $totalOutOfStock = $groupHistory->number_out_of_stock_org_stocks;
        $totalLocations  = $groupHistory->number_locations;
        $stockValue      = (float) $groupHistory->grp_stock_value;
        $dormant1y       = (float) $groupHistory->grp_value_dormant_stock_1y;
        $totalNotSold1y  = $orgHistories->sum(fn ($item) => $item['history']->number_org_stocks_not_sold_1y);

        $pctOutOfStock = $totalSkus > 0 ? round($totalOutOfStock / $totalSkus * 100, 1) : 0;
        $pctDormant1y  = $groupHistory->percentage_value_dormant_stock_1y ?? 0;
        $pctNotSold1y  = $totalSkus > 0 ? round($totalNotSold1y / $totalSkus * 100, 1) : 0;

        return [
            'date'                           => $groupHistory->date->toDateString(),
            'number_org_stocks'              => $totalSkus,
            'number_out_of_stock_org_stocks' => $totalOutOfStock,
            'percentage_out_of_stock'        => $pctOutOfStock,
            'number_locations'               => $totalLocations,
            'grp_stock_value'                => $stockValue,
            'currency_code'                  => $group->currency->code,
            'grp_value_dormant_stock_1y'     => $dormant1y,
            'percentage_dormant_1y'          => $pctDormant1y,
            'number_org_stocks_not_sold_1y'  => $totalNotSold1y,
            'percentage_not_sold_1y'         => $pctNotSold1y,
            'organisations'                  => $orgHistories->map(fn ($item) => [
                'name'                           => $item['org']->name,
                'slug'                           => $item['org']->slug,
                'currency_code'                  => $item['org']->currency->code,
                'number_org_stocks'              => $item['history']->number_org_stocks,
                'number_out_of_stock_org_stocks' => $item['history']->number_out_of_stock_org_stocks,
                'percentage_out_of_stock'        => $item['history']->number_org_stocks > 0
                    ? round($item['history']->number_out_of_stock_org_stocks / $item['history']->number_org_stocks * 100, 1)
                    : 0,
                'number_locations'               => $item['history']->number_locations,
                'org_stock_value'                => (float) $item['history']->org_stock_value,
                'value_dormant_stock_1y'         => (float) $item['history']->value_dormant_stock_1y,
                'percentage_dormant_1y'          => $item['history']->percentage_value_dormant_stock_1y ?? 0,
                'number_org_stocks_not_sold_1y'  => $item['history']->number_org_stocks_not_sold_1y,
                'percentage_not_sold_1y'         => $item['history']->number_org_stocks > 0
                    ? round($item['history']->number_org_stocks_not_sold_1y / $item['history']->number_org_stocks * 100, 1)
                    : 0,
            ])->values()->toArray(),
        ];
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
