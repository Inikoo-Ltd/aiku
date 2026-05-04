<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Mar 2026 13:47:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\Discounts\UI\GetDiscountsDashboardTimeSeriesData;
use App\Http\Resources\Catalogue\OfferCampaignResource;
use App\Http\Resources\Dashboards\DashboardOffersResource;
use App\Models\Discounts\OfferCampaign;
use Lorisleiva\Actions\Concerns\AsObject;

class GetVolGRCampaignOverview
{
    use AsObject;

    public function handle(OfferCampaign $offerCampaign, $showAmnestyRoute, $editAmnestyRoute, $amnestyOffer): array
    {
        return [
            'offerCampaign'      => OfferCampaignResource::make($offerCampaign),
            'currency_code'      => $offerCampaign->shop->currency->code,
            'edit_amnesty_route' => $editAmnestyRoute,
            'show_amnesty_route' => $showAmnestyRoute,
            'amnesty_offer'      => $amnestyOffer,
            'tabsBox'       => $this->getTabsBox($offerCampaign),
        ];
    }

    public function getTabsBox(OfferCampaign $offerCampaign)
    {
        $stats = $offerCampaign->stats;
        $shop = $offerCampaign->shop;

        // $timeSeriesData = GetDiscountsDashboardTimeSeriesData::run($shop);
        // $offerCampaignTimeSeriesStats = $timeSeriesData['offerCampaigns'];

        // $timeSeriesStat = collect(DashboardOffersResource::collection($offerCampaignTimeSeriesStats)->resolve())->keyBy('slug');
        // $totalSales = data_get($timeSeriesStat, "{$offerCampaign->slug}.columns.sales_grp_currency_external.all.formatted_value");

        return [
           [
               'label'         => __('Offers'),
               'currency_code' => $shop->currency,
               'tabs'          => [
                   [
                       'tab_slug'    => 'offers',
                       'label'       => __('Offers'),
                       'value'       => $stats->number_offers,
                       'type'        => 'number',
                       'tooltip' => __('Total Offers'),
                       'icon_data'   => [
                           'icon'    => 'fal fa-tags',
                       ],
                       'visitRoute'  => [
                           'name'      => request()->route()->getName(),
                           'parameters'    => [
                               'organisation'      => $offerCampaign->organisation->slug,
                               'shop'              => $offerCampaign->shop->slug,
                               'offerCampaign'     => $offerCampaign->slug,
                               'tab'               => 'offers',
                           ]
                       ],
                       'information' => [
                           'label' => 'Total Offers'
                       ]
                   ]
               ],
           ],
           [
               'label'         => __('Active Offers'),
               'currency_code' => $shop->currency,
               'tabs'          => [
                   [
                       'tab_slug'    => 'in_process_offers',
                       'label'       => __('Active, not yet started'),
                       'value'       => $stats->number_offers_state_in_process,
                       'type'        => 'number',
                       'tooltip' => __('Active, not yet started'),
                       'icon_data'   => [
                           'icon'    => 'fal fa-spinner',
                       ],
                       'visitRoute'  => [
                           'name'      => request()->route()->getName(),
                           'parameters'    => [
                               'organisation'      => $offerCampaign->organisation->slug,
                               'shop'              => $offerCampaign->shop->slug,
                               'offerCampaign'     => $offerCampaign->slug,
                               'tab'               => 'offers',
                               'offers_elements'   =>  [
                                   'state'         =>  'in_process'
                               ],
                           ]
                       ],
                       'information' => [
                           'label' => 'In Process'
                       ]
                   ],
                   [
                       'tab_slug'    => 'active_offers',
                       'label'       => __('Active, started offers'),
                       'value'       => $stats->number_offers_state_active,
                       'type'        => 'number',
                       'tooltip' => __('Active, started offers'),
                       'icon_data'   => [
                           'icon'    => 'fal fa-play',
                       ],
                       'visitRoute'  => [
                           'name'      => request()->route()->getName(),
                           'parameters'    => [
                               'organisation'      => $offerCampaign->organisation->slug,
                               'shop'              => $offerCampaign->shop->slug,
                               'offerCampaign'     => $offerCampaign->slug,
                               'tab'               => 'offers',
                               'offers_elements'   =>  [
                                   'state'         =>  'active'
                               ],
                           ]
                       ],
                       'information' => [
                           'label' => 'Active'
                       ]
                   ]
               ],
           ],
           [
               'label'         => __('Ended Offers'),
               'currency_code' => $shop->currency,
               'tabs'          => [
                   [
                       'tab_slug'    => 'ended_offers',
                       'label'       => __('Finished Offer'),
                       'value'       => $stats->number_offers_state_finished,
                       'type'        => 'number',
                       'tooltip' => __('Finished Offer'),
                       'icon_data'   => [
                           'icon'    => 'fal fa-check',
                       ],
                       'visitRoute'  => [
                           'name'      => request()->route()->getName(),
                           'parameters'    => [
                               'organisation'      => $offerCampaign->organisation->slug,
                               'shop'              => $offerCampaign->shop->slug,
                               'offerCampaign'     => $offerCampaign->slug,
                               'tab'               => 'offers',
                               'offers_elements'   =>  [
                                   'state'         =>  'finished'
                               ],
                           ]
                       ],
                       'information' => [
                           'label' => 'Finished'
                       ]
                   ]
               ],
           ],
           [
               'label'         => __('Used By'),
               'currency_code' => $shop->currency,
               'tabs'          => [
                   [
                       'tab_slug'    => 'customers',
                       'label'       => __('Customers'),
                       'value'       => $stats->number_customers,
                       'type'        => 'number',
                       'tooltip' => __('Customers'),
                       'icon_data'   => [
                           'icon'    => 'fal fa-users',
                       ],
                       'information' => [
                           'label' => 'Customer Usage'
                       ]
                   ],
                   [
                       'tab_slug'    => 'orders',
                       'label'       => __('Orders'),
                       'value'       => $stats->number_orders,
                       'type'        => 'number',
                       'tooltip' => __('Orders'),
                       'icon_data'   => [
                           'icon'    => 'fal fa-shopping-cart',
                       ],
                       'information' => [
                           'label' => 'Order Usage'
                       ]
                   ]
               ],
           ],
        ];
    }
}
