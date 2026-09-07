<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 29 Aug 2026 18:00:00 Central European Summer Time, Bratislava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\Settings\WithDashboardCurrencyTypeSettings;
use App\Actions\Traits\Dashboards\Settings\WithDashboardDataDisplayTypeSettings;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithPerformanceDateResolution;
use App\Enums\Dashboards\ShopDashboardSalesTableTabsEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The brand sales table evicted from the dropshipping shop dashboard: same data, same
 * renderer, its own page behind the Brands link.
 */
class ShowShopBrandsDashboard extends OrgAction
{
    use WithDashboardCurrencyTypeSettings;
    use WithDashboardDataDisplayTypeSettings;
    use WithDashboardIntervalOption;
    use WithPerformanceDateResolution;

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        $userSettings = $request->user()->settings;

        $savedInterval = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;
        [$fromDate, $toDate] = $this->resolvePerformanceDates($savedInterval, $userSettings);

        $timeSeriesData = GetShopDashboardTimeSeriesData::run($shop, $fromDate, $toDate);

        $tab       = ShopDashboardSalesTableTabsEnum::BRANDS;
        $dashboard = [
            'super_blocks' => [
                [
                    'id'        => 'shop_brands_tab',
                    'intervals' => [
                        'options'        => $this->dashboardIntervalOption(),
                        'value'          => $savedInterval,
                        'range_interval' => DashboardIntervalFilters::run($savedInterval, $userSettings)
                    ],
                    'settings'  => [
                        'data_display_type' => $this->dashboardDataDisplayTypeSettings($userSettings),
                        'currency_type'     => $this->dashboardCurrencyTypeSettings($this->organisation, $userSettings),
                    ],
                    'blocks'    => [
                        [
                            'id'              => 'sales_table',
                            'type'            => 'table',
                            'current_tab'     => $tab->value,
                            'tabs'            => [$tab->value => $tab->blueprint()],
                            'tables'          => [$tab->value => $tab->table($shop, $timeSeriesData)],
                            'charts'          => [],
                            'tab_fetch_route' => [
                                'name'       => 'grp.org.shops.show.dashboard.tab-data',
                                'parameters' => ['organisation' => $this->organisation->slug, 'shop' => $shop->slug],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return Inertia::render('Org/Catalogue/Shop', [
            'title'       => __('Brands').' '.$shop->code,
            'breadcrumbs' => array_merge(
                (new ShowShop())->getBreadcrumbs($request->route()->originalParameters()),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.dashboard.brands',
                                'parameters' => $request->route()->originalParameters()
                            ],
                            'label' => __('Brands'),
                            'icon'  => 'fal fa-copyright',
                        ],
                    ],
                ]
            ),
            'dashboard'   => $dashboard,
        ]);
    }
}
