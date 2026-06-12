<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Nov 2025 19:43:12 Malaysia Time, Plane KL - Bali
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Discounts\OfferAllowance\SuspendOfferAllowance;
use App\Actions\Discounts\OfferCampaign\Hydrators\OfferCampaignHydrateOffersState;
use App\Actions\Ordering\Order\CleanFinishedVouchers;
use App\Actions\Ordering\Order\RecalculateCustomerTotalsOrdersInBasket;
use App\Actions\Ordering\Order\RecalculateShopOrderDiscountsInBasket;
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
        if ($offer->state == OfferStateEnum::SUSPENDED || $offer->state == OfferStateEnum::FINISHED) {
            return $offer;
        }

        if ($offer->state != OfferStateEnum::ACTIVE) {
            abort(422);
        }

        $currentStatus = $offer->status;

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
        OfferCampaignHydrateOffersState::run($offer->offerCampaign);

        if ($currentStatus != $offer->status) {
            if ($offer->voucher) {
                CleanFinishedVouchers::run($offer->id);
            }

            if ($offer->trigger_type == 'ProductCategory') {
                UpdateProductCategoryOffersData::run($offer);
            }

            if ($offer->customer_id) {
                RecalculateCustomerTotalsOrdersInBasket::dispatch($offer->customer_id)->delay(now()->addSeconds(10));
            } else {
                RecalculateShopOrderDiscountsInBasket::dispatch($offer->shop_id)->delay(now()->addSeconds(10));
            }
        }

        return $offer;
    }

    public function asController(Offer $offer, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($offer->shop, $request);

        return $this->handle($offer);
    }


}
