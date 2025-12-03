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
use App\Models\Discounts\TransactionHasOfferAllowance;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
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
            'number_orders' => TransactionHasOfferAllowance::query()
                ->where('offer_campaign_id', $offerCampaign->id)
                ->count(DB::raw('DISTINCT order_id')),
        ];

        $offerCampaign->stats()->update($stats);
    }

}
