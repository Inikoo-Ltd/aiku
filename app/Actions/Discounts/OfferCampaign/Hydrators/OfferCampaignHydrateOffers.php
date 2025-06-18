<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Sept 2024 20:39:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OfferCampaignHydrateOffers implements ShouldBeUnique
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
            'number_offers'         => $offerCampaign->offers()->count(),
            'number_current_offers' => $offerCampaign->offers()->where('status', true)->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'offers',
                field: 'state',
                enum: OfferStateEnum::class,
                models: Offer::class,
                where: function ($q) use ($offerCampaign) {
                    $q->where('offer_campaign_id', $offerCampaign->id);
                }
            )
        );

        $offerCampaign->stats()->update($stats);
    }


}
