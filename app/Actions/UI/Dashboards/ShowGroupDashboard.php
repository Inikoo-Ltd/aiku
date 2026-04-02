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
                    'inventory_snapshot' => $this->getInventorySnapshot($group),
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
                'title'       => __('Dashboard Group'),
                'breadcrumbs' => $this->getBreadcrumbs(__('Dashboard')),
                'dashboard'   => $dashboard
            ]
        );
    }

    public function asController(ActionRequest $request): Response
    {
        $group = group();

        $this->initialisationFromGroup($group, $request);

        return $this->handle($group, $request);
    }

    private function getInventorySnapshot(Group $group): array
    {
        $group->loadMissing(['organisations' => fn ($q) => $q->with(['currency', 'warehouses'])]);

        $orgIds = $group->organisations->pluck('id');

        $latestHistories = OrganisationStockHistory::query()
            ->whereIn('organisation_id', $orgIds)
            ->where('is_week', false)
            ->where('is_month', false)
            ->where('is_year', false)
            ->whereRaw('date = (
                SELECT MAX(osh2.date)
                FROM organisation_stock_histories osh2
                WHERE osh2.organisation_id = organisation_stock_histories.organisation_id
                AND osh2.is_week = false AND osh2.is_month = false AND osh2.is_year = false
            )')
            ->get()
            ->keyBy('organisation_id');

        $rows = [];
        foreach ($group->organisations as $organisation) {
            $history      = $latestHistories->get($organisation->id);
            $currencyCode = $organisation->currency->code;
            $totalSkus    = $history?->number_org_stocks ?? 0;
            $stockValue   = (float) ($history?->org_stock_value ?? 0);
            $warehouse    = $organisation->warehouses->first();

            $historyRouteParams = $history && $warehouse ? [
                'organisation'             => $organisation->slug,
                'warehouse'                => $warehouse->slug,
                'organisationStockHistory' => $history->id,
            ] : null;

            $rows[] = [
                'name'                           => $organisation->name,
                'slug'                           => $organisation->slug,
                'currency_code'                  => $currencyCode,
                'date'                           => $history?->date?->toDateString(),
                'number_org_stocks'              => $history ? number_format($totalSkus) : '--',
                'number_out_of_stock_org_stocks' => $history ? number_format($history->number_out_of_stock_org_stocks) : '--',
                'percentage_out_of_stock'        => $totalSkus > 0
                    ? round($history->number_out_of_stock_org_stocks / $totalSkus * 100, 1)
                    : 0,
                'number_locations'               => $history ? number_format($history->number_locations) : '--',
                'org_stock_value'                => $history ? $stockValue : null,
                'number_org_stocks_not_sold_1y'  => $history ? number_format($history->number_org_stocks_not_sold_1y) : '--',
                'percentage_not_sold_1y'         => $totalSkus > 0
                    ? round($history->number_org_stocks_not_sold_1y / $totalSkus * 100, 1)
                    : 0,
                'value_dormant_stock_1y'         => $history ? (float) $history->value_dormant_stock_1y : null,
                'percentage_dormant_1y'          => $stockValue > 0
                    ? round((float) $history->value_dormant_stock_1y / $stockValue * 100, 1)
                    : 0,
                'history_route'   => $historyRouteParams ? [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stock_histories.show',
                    'parameters' => $historyRouteParams,
                ] : null,
                'locations_route' => $warehouse ? [
                    'name'       => 'grp.org.warehouses.show.infrastructure.locations.index',
                    'parameters' => [
                        'organisation' => $organisation->slug,
                        'warehouse'    => $warehouse->slug,
                    ],
                ] : null,
            ];
        }

        return $rows;
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
