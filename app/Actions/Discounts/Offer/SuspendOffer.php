<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Nov 2025 19:43:12 Malaysia Time, Plane KL - Bali
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Discounts\OfferAllowance\SuspendOfferAllowance;
use App\Actions\Discounts\OfferCampaign\Hydrators\OfferCampaignHydrateStateFromOffers;
use App\Actions\Ordering\Order\RecalculateShopTotalsOrdersInBasket;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Models\Discounts\Offer;
use Lorisleiva\Actions\ActionRequest;

class SuspendOffer extends OrgAction
{
    use WithActionUpdate;


    public function handle(Offer $offer): Offer
    {
        $modelData = [
            'state'             => OfferStateEnum::SUSPENDED,
            'status'            => false,
            'last_suspended_at' => now(),
        ];


        foreach ($offer->offerAllowances as $offerAllowance) {
            if ($offerAllowance->state == OfferAllowanceStateEnum::ACTIVE) {
                SuspendOfferAllowance::run($offerAllowance);
            }
        }


        $offer->update($modelData);
        OfferCampaignHydrateStateFromOffers::run($offer->offerCampaign);

        RecalculateShopTotalsOrdersInBasket::dispatch($offer->shop_id);

        return $offer;
    }


    public function asController(Offer $offer, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($offer->shop, $request);

        return $this->handle($offer);
    }


}
