<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Nov 2025 19:24:13 Malaysia Time, Plane KL - Bali
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Discounts\OfferAllowance\ActivateOfferAllowance;
use App\Actions\Ordering\Order\RecalculateShopTotalsOrdersInBasket;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Models\Discounts\Offer;
use Lorisleiva\Actions\ActionRequest;

class ActivateOffer extends OrgAction
{
    use WithActionUpdate;


    public function handle(Offer $offer): Offer
    {
        $modelData = [
            'state'  => OfferStateEnum::ACTIVE,
            'status' => true
        ];

        if (!$offer->start_at) {
            data_set($modelData, 'start_at', now());
        }

        foreach ($offer->offerAllowances as $offerAllowance) {
            if ($offerAllowance->state == OfferAllowanceStateEnum::SUSPENDED || $offerAllowance->state == OfferAllowanceStateEnum::IN_PROCESS) {
                ActivateOfferAllowance::run($offerAllowance);
            }
        }

        $offer->update($modelData);
        UpdateOfferAllowanceSignature::run($offer);

        RecalculateShopTotalsOrdersInBasket::dispatch($offer->shop_id)->delay(now()->addSeconds(10));

        return $offer;
    }


    public function asController(Offer $offer, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($offer->shop, $request);

        return $this->handle($offer);
    }


}
