<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Sept 2024 22:16:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Discounts\OfferCampaign\OfferCampaignStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateOfferCampaigns implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function handle(Shop $shop): void
    {
        $stats = [
            'number_offer_campaigns'         => $shop->offerCampaigns()->count(),
            'number_current_offer_campaigns' => $shop->offerCampaigns()->where('status', true)->count(),

        ];


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'offer_campaigns',
                field: 'state',
                enum: OfferCampaignStateEnum::class,
                models: OfferCampaign::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id);
                }
            )
        );

        $shop->discountsStats()->update($stats);
    }


}
