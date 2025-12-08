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
use App\Http\Resources\Catalogue\Shop\ShopIntervalsResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowShop extends OrgAction
{
    use WithDashboard;
    use WithDashboardSettings;
    use WithDashboardIntervalOption;
    use WithDashboardCurrencyTypeSettings;
    use WithTabsBox;

    public function handle(Shop $shop, ActionRequest $request): Response
    {
        $userSettings = $request->user()->settings;

        $currentTab = Arr::get($userSettings, 'shop_dashboard_tab', Arr::first(ShopDashboardSalesTableTabsEnum::values()));

        if (!in_array($currentTab, ShopDashboardSalesTableTabsEnum::values())) {
            $currentTab = Arr::first(ShopDashboardSalesTableTabsEnum::values());
        }

        $saved_interval = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;

        if ($saved_interval === DateIntervalEnum::CUSTOM) {
            $saved_interval = DateIntervalEnum::ALL;
        }

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
                    'shop_blocks' => array_merge(
                        [
                            'interval_data' => json_decode(ShopIntervalsResource::make($shop)->toJson()),
                            'currency_code' => $shop->currency->code,
                        ],
                        $this->getAverageClv($shop)
                    ),
                    'tabs_box' => [
                        'current'    => $this->tab,
                        'navigation' => $tabsBox
                    ],
                ],
            ],
        ];

        if ($shop->type->value === 'dropshipping') {
            $dashboard['super_blocks'][0]['blocks'] = [
                [
                    'id'          => 'sales_table',
                    'type'        => 'table',
                    'current_tab' => $currentTab,
                    'tabs'        => ShopDashboardSalesTableTabsEnum::navigation($shop),
                    'tables'      => ShopDashboardSalesTableTabsEnum::tables($shop),
                    'charts'      => []
                ]
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

    public function getAverageClv(Shop $shop): array
    {
        $clvData = DB::table('customers as c')
            ->join('customer_stats as cs', 'c.id', '=', 'cs.customer_id')
            ->where('c.shop_id', $shop->id)
            ->selectRaw('
                AVG(CASE WHEN cs.total_clv_amount > 0 THEN cs.total_clv_amount ELSE 0 END) as avg_clv,
                AVG(CASE WHEN cs.historic_clv_amount > 0 THEN cs.historic_clv_amount ELSE 0 END) as avg_historic_clv,
                COUNT(CASE WHEN cs.total_clv_amount > 0 THEN 1 END) as clv_count,
                COUNT(CASE WHEN cs.historic_clv_amount > 0 THEN 1 END) as historic_clv_count
            ')
            ->first();

        $averageCLV = $clvData->avg_clv ?? 0;
        $averageHistoricCLV = $clvData->avg_historic_clv ?? 0;

        return [
            'average_clv' => $averageCLV,
            'average_historic_clv' => $averageHistoricCLV
        ];
    }
}
