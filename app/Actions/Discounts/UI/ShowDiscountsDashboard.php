<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 05 Jan 2025 14:07:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\Settings\WithDashboardCurrencyTypeSettings;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithDashboardSettings;
use App\Actions\Traits\WithDashboard;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\UI\Discounts\DiscountsDashboardTabsEnum;
use App\Http\Resources\Dashboards\DashboardHeaderOffersResource;
use App\Http\Resources\Dashboards\DashboardOffersResource;
use App\Http\Resources\Dashboards\DashboardTotalOffersResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowDiscountsDashboard extends OrgAction
{
    use WithDashboard;
    use WithDashboardSettings;
    use WithDashboardIntervalOption;
    use WithDashboardCurrencyTypeSettings;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("discounts.{$this->shop->id}.view");
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): ActionRequest
    {
        $this->initialisationFromShop($shop, $request);

        return $request;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $userSettings = $request->user()->settings;

        $saved_interval = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;

        if ($saved_interval === DateIntervalEnum::CUSTOM) {
            $saved_interval = DateIntervalEnum::ALL;
        }

        return Inertia::render(
            'Org/Discounts/DiscountsDashboard',
            [
                'breadcrumbs'  => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'        => __('Offers Dashboard'),
                'pageHead'     => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-badge-percent'],
                        'title' => __('Offers Dashboard')
                    ],
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'title' => __('Offer')
                    ],
                    'title'     => __('Offers Dashboard'),
                ],
                'intervals' => [
                    'options'        => $this->dashboardIntervalOption(),
                    'value'          => $saved_interval,
                    'range_interval' => DashboardIntervalFilters::run($saved_interval, $userSettings),
                ],
                'settings'  => [
                    'model_state_type'  => $this->dashboardModelStateTypeSettings($userSettings, 'left'),
                    'data_display_type' => $this->dashboardDataDisplayTypeSettings($userSettings),
                    'currency_type'     => $this->dashboardCurrencyTypeSettings($this->organisation, $userSettings),
                ],
                'tabs'          => [
                    'current'    => $this->tab,
                    'navigation' => DiscountsDashboardTabsEnum::navigation()
                ],
                'blocks'        => [
                    'id'          => 'sales_table',
                    'type'        => 'table',
                    'current_tab' => 'offers',
                    'tabs'        => [
                        'offers' => [
                            'title' => 'Offers',
                        ],
                    ],
                    'tables'      => [
                        'offers' => [
                            'header' => json_decode(DashboardHeaderOffersResource::make($this->shop)->toJson(), true),
                            'body'   => json_decode(DashboardOffersResource::collection($this->getDummyData())->toJson(), true),
                            'totals' => json_decode(DashboardTotalOffersResource::make($this->getDummyData())->toJson(), true),
                        ],
                    ],
                ],
                'stats'         => [
                    [
                        'name'      => __('Campaigns'),
                        'value'     => $this->shop->discountsStats->number_current_offer_campaigns,
                        'icon'      => 'fal fa-comment-dollar',
                        'route'     => [
                            'name'       => 'grp.org.shops.show.discounts.campaigns.index',
                            'parameters' => $request->route()->originalParameters()
                        ]
                    ],
                    [
                        'name'      => __('Offers'),
                        'value'     => $this->shop->discountsStats->number_offers,
                        'icon'      => 'fal fa-badge-percent',
                        'route'     => [
                            'name'       => 'grp.org.shops.show.discounts.offers.index',
                            'parameters' => $request->route()->originalParameters()
                        ]
                    ],
                ]
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.discounts.dashboard',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Offers')
                    ]
                ]
            ]
        );
    }

    private function getDummyData(): array
    {
        return [
            [
                "id" => 997,
                "shop_id" => 42,
                "customers_all" => 154302,
                "customers_1y" => 22991,
                "customers_1q" => 6364,
                "customers_1m" => 2250,
                "customers_1w" => 32,
                "customers_3d" => 3,
                "customers_1d" => 0,
                "customers_ytd" => 19592,
                "customers_qtd" => 3540,
                "customers_mtd" => 1085,
                "customers_wtd" => 3,
                "customers_tdy" => 0,
                "customers_lm" => 2459,
                "customers_lw" => 777,
                "customers_ld" => 106,
                "customers_1y_ly" => 19129,
                "customers_1q_ly" => 4869,
                "customers_1m_ly" => 1659,
                "customers_1w_ly" => 444,
                "customers_3d_ly" => 166,
                "customers_1d_ly" => 0,
                "customers_ytd_ly" => 15313,
                "customers_qtd_ly" => 1989,
                "customers_mtd_ly" => 227,
                "customers_wtd_ly" => 7,
                "customers_tdy_ly" => 7,
                "customers_lm_ly" => 1762,
                "customers_lw_ly" => 425,
                "customers_ld_ly" => 2,
                "customers_py1" => 19474,
                "customers_py2" => 19629,
                "customers_py3" => 25439,
                "customers_py4" => 35508,
                "customers_py5" => 34675,
                "customers_pq1" => 5846,
                "customers_pq2" => 4952,
                "customers_pq3" => 5278,
                "customers_pq4" => 6150,
                "customers_pq5" => 4501,
                "orders_all" => 497820,
                "orders_1y" => 72991,
                "orders_1q" => 19364,
                "orders_1m" => 6250,
                "orders_1w" => 132,
                "orders_3d" => 13,
                "orders_1d" => 2,
                "orders_ytd" => 59592,
                "orders_qtd" => 12540,
                "orders_mtd" => 3085,
                "orders_wtd" => 23,
                "orders_tdy" => 1,
                "orders_lm" => 5459,
                "orders_lw" => 1777,
                "orders_ld" => 206,
                "orders_1y_ly" => 69129,
                "orders_1q_ly" => 16869,
                "orders_1m_ly" => 4659,
                "orders_1w_ly" => 944,
                "orders_3d_ly" => 266,
                "orders_1d_ly" => 5,
                "orders_ytd_ly" => 55313,
                "orders_qtd_ly" => 11989,
                "orders_mtd_ly" => 1227,
                "orders_wtd_ly" => 17,
                "orders_tdy_ly" => 17,
                "orders_lm_ly" => 4762,
                "orders_lw_ly" => 1425,
                "orders_ld_ly" => 12,
                "orders_py1" => 69474,
                "orders_py2" => 69629,
                "orders_py3" => 75439,
                "orders_py4" => 85508,
                "orders_py5" => 84675,
                "orders_pq1" => 15846,
                "orders_pq2" => 14952,
                "orders_pq3" => 15278,
                "orders_pq4" => 16150,
                "orders_pq5" => 14501,
                "created_at" => "2025-01-01 03:44:50+08",
                "updated_at" => "2025-11-18 00:19:09+08"
            ],
            [
                "id" => 998,
                "shop_id" => 42,
                "customers_all" => 89234,
                "customers_1y" => 15678,
                "customers_1q" => 4231,
                "customers_1m" => 1489,
                "customers_1w" => 45,
                "customers_3d" => 8,
                "customers_1d" => 1,
                "customers_ytd" => 12876,
                "customers_qtd" => 2314,
                "customers_mtd" => 765,
                "customers_wtd" => 12,
                "customers_tdy" => 0,
                "customers_lm" => 1623,
                "customers_lw" => 523,
                "customers_ld" => 78,
                "customers_1y_ly" => 14231,
                "customers_1q_ly" => 3856,
                "customers_1m_ly" => 1321,
                "customers_1w_ly" => 312,
                "customers_3d_ly" => 98,
                "customers_1d_ly" => 2,
                "customers_ytd_ly" => 11892,
                "customers_qtd_ly" => 1654,
                "customers_mtd_ly" => 189,
                "customers_wtd_ly" => 9,
                "customers_tdy_ly" => 9,
                "customers_lm_ly" => 1245,
                "customers_lw_ly" => 298,
                "customers_ld_ly" => 5,
                "customers_py1" => 14892,
                "customers_py2" => 15267,
                "customers_py3" => 16894,
                "customers_py4" => 19234,
                "customers_py5" => 18765,
                "customers_pq1" => 3856,
                "customers_pq2" => 3245,
                "customers_pq3" => 3567,
                "customers_pq4" => 4123,
                "customers_pq5" => 2987,
                "orders_all" => 287563,
                "orders_1y" => 42367,
                "orders_1q" => 11234,
                "orders_1m" => 3654,
                "orders_1w" => 89,
                "orders_3d" => 15,
                "orders_1d" => 3,
                "orders_ytd" => 34567,
                "orders_qtd" => 7234,
                "orders_mtd" => 1890,
                "orders_wtd" => 34,
                "orders_tdy" => 2,
                "orders_lm" => 3123,
                "orders_lw" => 987,
                "orders_ld" => 145,
                "orders_1y_ly" => 39876,
                "orders_1q_ly" => 9876,
                "orders_1m_ly" => 2876,
                "orders_1w_ly" => 623,
                "orders_3d_ly" => 156,
                "orders_1d_ly" => 7,
                "orders_ytd_ly" => 31234,
                "orders_qtd_ly" => 6456,
                "orders_mtd_ly" => 876,
                "orders_wtd_ly" => 23,
                "orders_tdy_ly" => 23,
                "orders_lm_ly" => 2654,
                "orders_lw_ly" => 712,
                "orders_ld_ly" => 18,
                "orders_py1" => 41234,
                "orders_py2" => 39876,
                "orders_py3" => 42345,
                "orders_py4" => 48765,
                "orders_py5" => 47654,
                "orders_pq1" => 9234,
                "orders_pq2" => 8456,
                "orders_pq3" => 8765,
                "orders_pq4" => 9456,
                "orders_pq5" => 8234,
                "created_at" => "2024-06-15 10:30:25+08",
                "updated_at" => "2025-11-17 14:22:33+08"
            ],
            [
                "id" => 999,
                "shop_id" => 42,
                "customers_all" => 234567,
                "customers_1y" => 34567,
                "customers_1q" => 9876,
                "customers_1m" => 3210,
                "customers_1w" => 87,
                "customers_3d" => 12,
                "customers_1d" => 4,
                "customers_ytd" => 28765,
                "customers_qtd" => 6543,
                "customers_mtd" => 1987,
                "customers_wtd" => 21,
                "customers_tdy" => 1,
                "customers_lm" => 2987,
                "customers_lw" => 876,
                "customers_ld" => 123,
                "customers_1y_ly" => 31234,
                "customers_1q_ly" => 8765,
                "customers_1m_ly" => 2876,
                "customers_1w_ly" => 543,
                "customers_3d_ly" => 134,
                "customers_1d_ly" => 6,
                "customers_ytd_ly" => 26543,
                "customers_qtd_ly" => 5876,
                "customers_mtd_ly" => 765,
                "customers_wtd_ly" => 15,
                "customers_tdy_ly" => 15,
                "customers_lm_ly" => 2345,
                "customers_lw_ly" => 654,
                "customers_ld_ly" => 9,
                "customers_py1" => 33456,
                "customers_py2" => 32345,
                "customers_py3" => 35678,
                "customers_py4" => 41234,
                "customers_py5" => 39876,
                "customers_pq1" => 7654,
                "customers_pq2" => 6876,
                "customers_pq3" => 7234,
                "customers_pq4" => 7987,
                "customers_pq5" => 6456,
                "orders_all" => 723456,
                "orders_1y" => 98765,
                "orders_1q" => 26543,
                "orders_1m" => 8765,
                "orders_1w" => 234,
                "orders_3d" => 32,
                "orders_1d" => 8,
                "orders_ytd" => 87654,
                "orders_qtd" => 19876,
                "orders_mtd" => 5432,
                "orders_wtd" => 67,
                "orders_tdy" => 3,
                "orders_lm" => 7654,
                "orders_lw" => 1987,
                "orders_ld" => 234,
                "orders_1y_ly" => 92345,
                "orders_1q_ly" => 24567,
                "orders_1m_ly" => 7654,
                "orders_1w_ly" => 1234,
                "orders_3d_ly" => 298,
                "orders_1d_ly" => 12,
                "orders_ytd_ly" => 82345,
                "orders_qtd_ly" => 18765,
                "orders_mtd_ly" => 4321,
                "orders_wtd_ly" => 54,
                "orders_tdy_ly" => 54,
                "orders_lm_ly" => 6876,
                "orders_lw_ly" => 1765,
                "orders_ld_ly" => 23,
                "orders_py1" => 94567,
                "orders_py2" => 92345,
                "orders_py3" => 98765,
                "orders_py4" => 112345,
                "orders_py5" => 109876,
                "orders_pq1" => 22345,
                "orders_pq2" => 19876,
                "orders_pq3" => 20987,
                "orders_pq4" => 23456,
                "orders_pq5" => 18765,
                "created_at" => "2023-12-01 08:15:42+08",
                "updated_at" => "2025-11-16 09:45:18+08"
            ]
        ];
    }
}
