<?php

/*
 * author Arya Permana - Kirin
 * created on 18-11-2024-13h-32m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Discounts\OfferCampaign\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OfferCampaignHydrateOrders implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(OfferCampaign $offerCampaign): string
    {
        return $offerCampaign->id;
    }


    public function handle(OfferCampaign $offerCampaign): void
    {
        $stats = [
            'number_orders'   => $offerCampaign->transactions()->distinct()->count('transaction_has_offer_allowances.order_id'),
        ];


        $offerCampaign->stats()->update($stats);
    }


}
