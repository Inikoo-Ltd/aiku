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
                    "icon"  => "fal fa-cube",
                    "value" => $stats->number_offers,
                ],
                [
                    "label" => "Current Offers",
                    "icon"  => "fal fa-cube",
                    "value" => $stats->number_current_offers,
                ],
                [
                    "label" => "Offers in Process",
                    "icon"  => "fal fa-cube",
                    "value" => $stats->number_offers_state_in_process,
                ],
                [
                    "label" => "Active Offers",
                    "icon"  => "fal fa-cube",
                    "value" => $stats->number_offers_state_active,
                ],
                [
                    "label" => "Finished Offers",
                    "icon"  => "fal fa-cube",
                    "value" => $stats->number_offers_state_finished,
                ],
                [
                    "label" => "Customers",
                    "icon"  => "fal fa-users",
                    "value" => $stats->number_customers,
                ],
                [
                    "label" => "Orders",
                    "icon"  => "fal fa-shopping-cart",
                    "value" => $stats->number_orders,
                ],
            ]
        ];
    }
}
