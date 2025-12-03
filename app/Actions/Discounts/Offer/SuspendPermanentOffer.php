<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Nov 2025 19:43:12 Malaysia Time, Plane KL - Bali
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Discounts\OfferAllowance\SuspendPermanentOfferAllowance;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Models\Discounts\Offer;
use Lorisleiva\Actions\ActionRequest;

class SuspendPermanentOffer extends OrgAction
{
    use WithActionUpdate;


    public function handle(Offer $offer): Offer
    {
        if ($offer->duration != OfferDurationEnum::PERMANENT) {
            abort(419);
        }

        $modelData = [
            'state'  => OfferStateEnum::SUSPENDED,
            'status' => false,
            'end_at' => now()
        ];



        foreach ($offer->offerAllowances as $offerAllowance) {
            if ($offerAllowance->state == OfferAllowanceStateEnum::ACTIVE) {
                SuspendPermanentOfferAllowance::run($offerAllowance);
            }
        }


        $offer->update($modelData);

        return $offer;
    }


    public function asController(Offer $offer, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($offer->shop, $request);

        return $this->handle($offer);
    }


}
