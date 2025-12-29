<?php

/*
 * Author: Vika Aqordi
 * Created on 29-12-2025-09h-51m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Http\Resources\Catalogue\OfferCampaignResource;
use App\Models\Discounts\OfferCampaign;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOfferCampaignWorkshop
{
    use AsObject;

    public function handle(OfferCampaign $offerCampaign): array
    {
        $stats = $offerCampaign->stats;

        return [
            // 'offerCampaign' => OfferCampaignResource::make($offerCampaign),
            'offerCampaign' => $offerCampaign,
        ];
    }
}
