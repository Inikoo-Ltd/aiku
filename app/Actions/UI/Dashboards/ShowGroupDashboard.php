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
                    'stock_snapshot_table' => $this->getOrganisationStockSnapshotTable($group),
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

    private function getOrganisationStockSnapshotTable(Group $group): array
    {
        $group->loadMissing('organisations.currency');

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

        $body = [];
        $totals = [
            'number_org_stocks'              => 0,
            'number_out_of_stock_org_stocks' => 0,
            'number_locations'               => 0,
            'number_org_stocks_not_sold_1y'  => 0,
        ];

        foreach ($group->organisations as $organisation) {
            $history     = $latestHistories->get($organisation->id);
            $currencyCode = $organisation->currency->code;

            $body[] = [
                'slug'    => $organisation->slug,
                'state'   => 'active',
                'columns' => [
                    'label'                          => [
                        'formatted_value' => $organisation->name,
                        'align'           => 'left',
                        'route_target'    => [
                            'name'       => 'grp.org.dashboard.show',
                            'parameters' => ['organisation' => $organisation->slug],
                        ],
                    ],
                    'number_org_stocks'              => [
                        'formatted_value' => $history ? number_format($history->number_org_stocks) : '--',
                    ],
                    'number_out_of_stock_org_stocks' => [
                        'formatted_value' => $history ? number_format($history->number_out_of_stock_org_stocks) : '--',
                    ],
                    'number_locations'               => [
                        'formatted_value' => $history ? number_format($history->number_locations) : '--',
                    ],
                    'org_stock_value'                => [
                        'formatted_value' => $history ? number_format((float) $history->org_stock_value, 2) : '--',
                        'tooltip'         => $currencyCode,
                    ],
                    'number_org_stocks_not_sold_1y'  => [
                        'formatted_value' => $history ? number_format($history->number_org_stocks_not_sold_1y) : '--',
                    ],
                    'value_dormant_stock_1y'         => [
                        'formatted_value' => $history ? number_format((float) $history->value_dormant_stock_1y, 2) : '--',
                        'tooltip'         => $currencyCode,
                    ],
                ],
            ];

            if ($history) {
                $totals['number_org_stocks']              += $history->number_org_stocks;
                $totals['number_out_of_stock_org_stocks'] += $history->number_out_of_stock_org_stocks;
                $totals['number_locations']               += $history->number_locations;
                $totals['number_org_stocks_not_sold_1y']  += $history->number_org_stocks_not_sold_1y;
            }
        }

        return [
            'id'     => 'stock_snapshot_table',
            'type'   => 'table',
            'tables' => [
                'organisations' => [
                    'header' => [
                        'columns' => [
                            'label'                          => [
                                'formatted_value'   => __('Organisation'),
                                'align'             => 'left',
                                'frozen'            => true,
                                'alignFrozen'       => 'left',
                                'currency_type'     => 'always',
                                'data_display_type' => 'always',
                            ],
                            'number_org_stocks'              => [
                                'formatted_value'   => __('Total SKUs'),
                                'icon'              => 'fal fa-box-open',
                                'align'             => 'right',
                                'currency_type'     => 'always',
                                'data_display_type' => 'always',
                            ],
                            'number_out_of_stock_org_stocks' => [
                                'formatted_value'   => __('Out of Stock'),
                                'icon'              => 'fal fa-times-circle',
                                'align'             => 'right',
                                'currency_type'     => 'always',
                                'data_display_type' => 'always',
                            ],
                            'number_locations'               => [
                                'formatted_value'   => __('Locations'),
                                'icon'              => 'fal fa-map-marker-alt',
                                'align'             => 'right',
                                'currency_type'     => 'always',
                                'data_display_type' => 'always',
                            ],
                            'org_stock_value'                => [
                                'formatted_value'   => __('Stock Value'),
                                'icon'              => 'fal fa-pallet-alt',
                                'tooltip'           => __('In organisation currency'),
                                'align'             => 'right',
                                'currency_type'     => 'always',
                                'data_display_type' => 'always',
                            ],
                            'number_org_stocks_not_sold_1y'  => [
                                'formatted_value'   => __('No Sold 1Y'),
                                'icon'              => 'fal fa-ban',
                                'tooltip'           => __('Number of SKUs not sold in more than 1 year'),
                                'align'             => 'right',
                                'currency_type'     => 'always',
                                'data_display_type' => 'always',
                            ],
                            'value_dormant_stock_1y'         => [
                                'formatted_value'   => __('Dormant 1Y'),
                                'icon'              => 'fal fa-skull-cow',
                                'tooltip'           => __('Value of dormant stock for more than 1 year, in organisation currency'),
                                'align'             => 'right',
                                'currency_type'     => 'always',
                                'data_display_type' => 'always',
                            ],
                        ],
                    ],
                    'body'   => $body,
                    'totals' => [
                        'columns' => [
                            'label'                          => ['formatted_value' => __('Total'), 'align' => 'left'],
                            'number_org_stocks'              => ['formatted_value' => number_format($totals['number_org_stocks'])],
                            'number_out_of_stock_org_stocks' => ['formatted_value' => number_format($totals['number_out_of_stock_org_stocks'])],
                            'number_locations'               => ['formatted_value' => number_format($totals['number_locations'])],
                            'org_stock_value'                => ['formatted_value' => '--'],
                            'number_org_stocks_not_sold_1y'  => ['formatted_value' => number_format($totals['number_org_stocks_not_sold_1y'])],
                            'value_dormant_stock_1y'         => ['formatted_value' => '--'],
                        ],
                    ],
                ],
            ],
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
