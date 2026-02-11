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

        $timeSeriesData = GetDiscountsDashboardTimeSeriesData::run($this->shop, $performanceDates[0], $performanceDates[1]);
        $offerCampaignTimeSeriesStats = $timeSeriesData['offerCampaigns'];

        return Inertia::render(
            'Org/Discounts/DiscountsDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Offers Dashboard'),
                'pageHead'    => [
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
                'intervals'   => [
                    'options'        => $this->dashboardIntervalOption(),
                    'value'          => $saved_interval,
                    'range_interval' => DashboardIntervalFilters::run($saved_interval, $userSettings),
                ],
                'settings'    => [
                    'model_state_type'  => $this->dashboardModelStateTypeSettings($userSettings, 'left'),
                    'data_display_type' => $this->dashboardDataDisplayTypeSettings($userSettings),
                    'currency_type'     => $this->dashboardCurrencyTypeSettings($this->organisation, $userSettings),
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => DiscountsDashboardTabsEnum::navigation()
                ],
                'blocks'      => [
                    'id'          => 'sales_table',
                    'type'        => 'table',
                    'current_tab' => 'offer_campaigns',
                    'tabs'        => [
                        'offer_campaigns' => [
                            'title' => 'Offer Campaigns',
                        ],
                    ],
                    'tables'      => [
                        'offer_campaigns' => [
                            'header' => json_decode(DashboardHeaderOffersResource::make($this->shop)->toJson(), true),
                            'body'   => json_decode(DashboardOffersResource::collection($offerCampaignTimeSeriesStats)->toJson(), true),
                            'totals' => json_decode(DashboardTotalOffersResource::make($offerCampaignTimeSeriesStats)->toJson(), true),
                        ],
                    ],
                ],
                'data'        => [
                    'currency' => $this->shop->currency
                ]
            ]
        );
    }

    private function getHeaderActions($offer, array $routeParameters): array
    {
        $actions = [];

        if (!$offer) {
            $actions[] = [
                'type'     => 'button',
                'style'    => 'create',
                'label'    => __('Create First Order Bonus'),
                'disabled' => true,
                'route'    => [
                    'name'       => 'grp.org.shops.show.discounts.offers.create',
                    'parameters' => $routeParameters
                ],
                // 'tooltip' => __('Create First Order Bonus')
                'tooltip'  => __('Create First Order Bonus (not available yet)')
            ];
        } else {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'edit',
                'label'   => __('Edit First Order Bonus'),
                'route'   => [
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
