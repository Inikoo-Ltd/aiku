<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 11:53:47 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Http\Resources\Catalogue\OfferCampaignResource;
use App\Models\Discounts\OfferCampaign;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOfferCampaignOverview
{
    use AsObject;

    public function handle(OfferCampaign $offerCampaign): array
    {
        $stats = $offerCampaign->stats;
        return [
            'offerCampaign' => OfferCampaignResource::make($offerCampaign),
            'currency_code' => $offerCampaign->shop->currency->code,
            'stats'   => [
                [
                    "label" => "Offers",
                    "icon"  => "fal fa-tags",
                    "value" => $stats->number_offers,
                    "route_target" => "?tab=offers"
                ],
                [
                    "label" => "Current Offers",
                    "icon"  => "fal fa-badge-percent",
                    "value" => $stats->number_current_offers,
                    "route_target" => null
                ],
                [
                    "label" => "Offers in Process",
                    "icon"  => "fal fa-spinner",
                    "value" => $stats->number_offers_state_in_process,
                    "route_target" => "?tab=offers&offers_elements[state]=in_process&offers_sort=id"
                ],
                [
                    "label" => "Active Offers",
                    "icon"  => "fal fa-play",
                    "value" => $stats->number_offers_state_active,
                    "route_target" => "?tab=offers&offers_elements[state]=active&offers_sort=id"
                ],
                [
                    "label" => "Finished Offers",
                    "icon"  => "fal fa-check",
                    "value" => $stats->number_offers_state_finished,
                    "route_target" => "?tab=offers&offers_elements[state]=finished&offers_sort=id"
                ],
                [
                    "label" => "Customers",
                    "icon"  => "fal fa-users",
                    "value" => $stats->number_customers,
                    "route_target" => null
                ],
                [
                    "label" => "Orders",
                    "icon"  => "fal fa-shopping-cart",
                    "value" => $stats->number_orders,
                    "route_target" => null
                ],
            ]
        ];
    }
}
