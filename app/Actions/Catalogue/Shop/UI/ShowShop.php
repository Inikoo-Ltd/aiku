<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 18 May 2023 14:27:38 Central European Summer, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\Settings\WithDashboardCurrencyTypeSettings;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithDashboardSettings;
use App\Actions\Traits\WithDashboard;
use App\Actions\Traits\WithTabsBox;
use App\Enums\Dashboards\ShopDashboardSalesTableTabsEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowShop extends OrgAction
{
    use WithDashboard;
    use WithDashboardCurrencyTypeSettings;
    use WithDashboardIntervalOption;
    use WithDashboardSettings;
    use WithTabsBox;

    public function handle(Shop $shop, ActionRequest $request): Response
    {
        $userSettings = $request->user()->settings;

        $currentTab = Arr::get($userSettings, 'shop_dashboard_tab', Arr::first(ShopDashboardSalesTableTabsEnum::values()));

        if (!in_array($currentTab, ShopDashboardSalesTableTabsEnum::values())) {
            $currentTab = Arr::first(ShopDashboardSalesTableTabsEnum::values());
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
        }

        $timeSeriesData = GetShopDashboardTimeSeriesData::run($shop, $performanceDates[0], $performanceDates[1]);
        $shopTimeSeriesStats = $timeSeriesData['shops'];

        $tabsBox = $this->getTabsBox($shop);

        $dashboard = [
            'super_blocks' => [
                [
                    'id'        => 'shop_dashboard_tab',
                    'intervals' => [
                        'options'        => $this->dashboardIntervalOption(),
                        'value'          => $saved_interval,
                        'range_interval' => DashboardIntervalFilters::run($saved_interval, $userSettings)
                    ],
                    'settings'  => [
                        'model_state_type'  => $this->dashboardModelStateTypeSettings($userSettings, 'left'),
                        'data_display_type' => $this->dashboardDataDisplayTypeSettings($userSettings),
                        'currency_type'     => $this->dashboardCurrencyTypeSettings($this->organisation, $userSettings)
                    ],
                    'shop_blocks' => [
                        'interval_data'        => $shopTimeSeriesStats,
                        'currency_code'        => $shop->currency->code,
                        'average_clv'          => $shop->stats->average_clv ?? 0,
                        'average_historic_clv' => $shop->stats->average_historic_clv ?? 0,
                    ],
                    'tabs_box' => [
                        'current'    => $this->tab,
                        'navigation' => $tabsBox
                    ],
                ],
            ],
        ];

        if ($shop->type->value === 'dropshipping' && isset($timeSeriesData['platforms'])) {
            $dashboard['super_blocks'][0]['blocks'] = [
                [
                    'id'          => 'sales_table',
                    'type'        => 'table',
                    'current_tab' => $currentTab,
                    'tabs'        => ShopDashboardSalesTableTabsEnum::navigation($shop),
                    'tables'      => ShopDashboardSalesTableTabsEnum::tables($shop, $timeSeriesData),
                    'charts'      => [],
                ],
            ];
        }

        return Inertia::render('Org/Catalogue/Shop', [
            'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
            'dashboard'   => $dashboard,
        ]);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $shop = Shop::where('slug', $routeParameters['shop'])->first();

        return array_merge(
            ShowOrganisationDashboard::make()->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.shops.index',
                                'parameters' => Arr::only($routeParameters, 'organisation')
                            ],
                            'label' => __('Shops'),
                            'icon'  => 'fal fa-bars'
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.dashboard.show',
                                'parameters' => Arr::only($routeParameters, ['organisation', 'shop']),
                            ],
                            'label' => $shop->code,
                            'icon'  => 'fal fa-bars',
                        ],
                    ],
                    'suffix' => $suffix,
                ],
            ],
        );
    }
}
