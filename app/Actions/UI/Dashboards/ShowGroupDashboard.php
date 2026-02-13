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
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        $topListedProducts = $this->getTopListedProducts($group);
        $topSoldProducts = $this->getTopSoldProducts($group);

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
                    'tabs_box'  => [
                        'current'    => $this->tab,
                        'navigation' => $tabsBox
                    ],
                ]
            ],
            'top_listed_products' => $topListedProducts,
            'top_sold_products' => $topSoldProducts,
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

    protected function getTopListedProducts(Group $group): array
    {
        $cacheKey = "dashboard_top_listed_products_group_{$group->id}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($group) {
            $topProducts = DB::table('portfolios')
                ->select(
                    'assets.id',
                    'assets.code',
                    'assets.name',
                    DB::raw('COUNT(portfolios.id) as total_listed')
                )
                ->join('assets', function ($join) {
                    $join->on('portfolios.item_id', '=', 'assets.id')
                        ->where('portfolios.item_type', '=', 'Product');
                })
                ->where('portfolios.group_id', $group->id)
                ->where('assets.type', 'product')
                ->whereNull('portfolios.last_removed_at')
                ->groupBy('assets.id', 'assets.code', 'assets.name')
                ->orderByDesc('total_listed')
                ->limit(1)
                ->get();

            return $topProducts->map(function ($product) {
                return [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'total_listed' => $product->total_listed,
                ];
            })->toArray();
        });
    }

    protected function getTopSoldProducts(Group $group): array
    {
        $cacheKey = "dashboard_top_sold_products_group_{$group->id}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($group) {
            $topProducts = DB::table('invoice_transactions')
                ->select(
                    'assets.id',
                    'assets.code',
                    'assets.name',
                    DB::raw('SUM(invoice_transactions.quantity) as total_sold'),
                    DB::raw('SUM(invoice_transactions.net_amount) as total_amount')
                )
                ->join('assets', function ($join) {
                    $join->on('invoice_transactions.model_id', '=', 'assets.id')
                        ->where('invoice_transactions.model_type', '=', 'Product');
                })
                ->where('invoice_transactions.group_id', $group->id)
                ->where('assets.type', 'product')
                ->whereNull('invoice_transactions.deleted_at')
                ->groupBy('assets.id', 'assets.code', 'assets.name')
                ->orderByDesc('total_sold')
                ->limit(1)
                ->get();

            return $topProducts->map(function ($product) {
                return [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'total_sold' => (float) $product->total_sold,
                    'total_amount' => (float) $product->total_amount,
                ];
            })->toArray();
        });
    }

    public static function clearTopProductsCache(Group $group): void
    {
        Cache::forget("dashboard_top_listed_products_group_{$group->id}");
        Cache::forget("dashboard_top_sold_products_group_{$group->id}");
    }
}
