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
use App\Actions\Traits\Dashboards\WithPerformanceDateResolution;
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
    use WithPerformanceDateResolution;
    use WithTabsBox;

    public function handle(Shop $shop): Shop
    {
        return $shop;
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $userSettings = $request->user()->settings;

        $tabsNavigation = ShopDashboardSalesTableTabsEnum::navigation($shop);
        $validTabs  = array_keys($tabsNavigation);
        $currentTab = Arr::get($userSettings, 'shop_dashboard_tab', Arr::first($validTabs));

        if (! in_array($currentTab, $validTabs, true)) {
            $currentTab = Arr::first($validTabs);
        }

        $savedInterval = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;
        [$fromDate, $toDate] = $this->resolvePerformanceDates($savedInterval, $userSettings);

        $timeSeriesData      = GetShopDashboardTimeSeriesData::run($shop, $fromDate, $toDate);
        $shopTimeSeriesStats = $timeSeriesData['shops'];

        $waitingItemsData = $this->buildWaitingItemsData($shop, $request);
        $tabsBox          = $this->getTabsBox($shop, $waitingItemsData);

        $dashboard = [
            'super_blocks' => [
                [
                    'id'        => 'shop_dashboard_tab',
                    'intervals' => [
                        'options'        => $this->dashboardIntervalOption(),
                        'value'          => $savedInterval,
                        'range_interval' => DashboardIntervalFilters::run($savedInterval, $userSettings)
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

        $currentTabEnum = ShopDashboardSalesTableTabsEnum::from($currentTab);
        $primaryTables  = ShopDashboardSalesTableTabsEnum::tablesForTabs($shop, $timeSeriesData, [$currentTabEnum]);

        $dashboard['super_blocks'][0]['blocks'] = [
            [
                'id'              => 'sales_table',
                'type'            => 'table',
                'current_tab'     => $currentTab,
                'tabs'            => $tabsNavigation,
                'tables'          => $primaryTables,
                'charts'          => [],
                'tab_fetch_route' => [
                    'name'       => 'grp.org.shops.show.dashboard.tab-data',
                    'parameters' => ['organisation' => $this->organisation->slug, 'shop' => $shop->slug],
                ],
            ],
        ];

        return Inertia::render('Org/Catalogue/Shop', [
            'title'            => __('Shop').' '.$shop->code,
            'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
            'dashboard'   => $dashboard,
        ]);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function getBreadcrumbs(array $routeParameters, ?string $suffix = null): array
    {
        $shop = Shop::query()->select('code')->where('slug', $routeParameters['shop'])->firstOrFail();

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
