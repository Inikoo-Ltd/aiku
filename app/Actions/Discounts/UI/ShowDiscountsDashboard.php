<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 05 Jan 2025 14:07:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Discounts\Offer\UI\GetShopOffersTimeSeriesStats;
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
use App\Models\Discounts\OfferCampaign;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;

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

        $hasFirstOrderCampaign = OfferCampaign::where('shop_id', $this->shop->id)
            ->where('type', OfferCampaignTypeEnum::FIRST_ORDER)
            ->exists();

        $firstOrderBonus = null;
        if ($hasFirstOrderCampaign) {
            $campaign = OfferCampaign::where('shop_id', $this->shop->id)
                ->where('type', OfferCampaignTypeEnum::FIRST_ORDER)
                ->first();

            $firstOrderBonus = $campaign->offers()->first();
        }

        $routeParameters = $request->route()->originalParameters();


        $timeSeriesStats = [];
        if ($saved_interval === DateIntervalEnum::CUSTOM) {
            $rangeInterval = Arr::get($userSettings, 'range_interval', '');
            if ($rangeInterval) {
                $dates = explode('-', $rangeInterval);
                if (count($dates) === 2) {
                    $timeSeriesStats = GetShopOffersTimeSeriesStats::run($this->shop, $dates[0], $dates[1]);
                }
            }
        } else {
            $timeSeriesStats = GetShopOffersTimeSeriesStats::run($this->shop);
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
                    'actions'   => $this->getHeaderActions($firstOrderBonus, $routeParameters),
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
                            'body'   => json_decode(DashboardOffersResource::collection($timeSeriesStats)->toJson(), true),
                            'totals' => json_decode(DashboardTotalOffersResource::make($timeSeriesStats)->toJson(), true),
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
                ],
                'data'  => [
                    'currency'  => $this->shop->currency
                ],
                'first_order_bonus' => $firstOrderBonus
            ]
        );
    }

    private function getHeaderActions($offer, array $routeParameters): array
    {
        $actions = [];

        if (!$offer) {
            $actions[] = [
                'type'  => 'button',
                'style' => 'create',
                'label' => __('Create First Order Bonus'),
                'disabled'  => true,
                'route' => [
                    'name'       => 'grp.org.shops.show.discounts.offers.create',
                    'parameters' => $routeParameters
                ],
                // 'tooltip' => __('Create First Order Bonus')
                'tooltip' => __('Create First Order Bonus (not available yet)')
            ];
        } else {
            $actions[] = [
                'type'  => 'button',
                'style' => 'edit',
                'label' => __('Edit First Order Bonus'),
                'route' => [
                    'name'       => 'grp.org.shops.show.discounts.offers.edit',
                    'parameters' => array_merge($routeParameters, [
                        'offer' => $offer->slug
                    ])
                ],
                'tooltip' => __('Edit existing First Order Bonus offer')
            ];
        }

        return $actions;
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
}
