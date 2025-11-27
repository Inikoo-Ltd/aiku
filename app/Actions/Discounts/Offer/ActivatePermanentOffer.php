<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Nov 2025 19:24:13 Malaysia Time, Plane KL - Bali
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Discounts\OfferAllowance\ActivatePermanentOfferAllowance;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Models\Discounts\Offer;
use Lorisleiva\Actions\ActionRequest;

class ActivatePermanentOffer extends OrgAction
{
    use WithActionUpdate;


    public function handle(Offer $offer): Offer
    {
        if ($offer->duration != OfferDurationEnum::PERMANENT) {
            abort(419);
        }

        $modelData = [
            'state'  => OfferStateEnum::ACTIVE,
            'status' => true
        ];

        if (!$offer->start_at) {
            data_set($modelData, 'start_at', now());
        }

        foreach ($offer->offerAllowances as $offerAllowance) {
            if ($offerAllowance->state == OfferAllowanceStateEnum::SUSPENDED || $offerAllowance->state == OfferAllowanceStateEnum::IN_PROCESS) {
                ActivatePermanentOfferAllowance::run($offerAllowance);
            }
        }

        $offer->update($modelData);
        UpdateOfferAllowanceSignature::run($offer);

        return $offer;
    }


    public function asController(Offer $offer, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($offer->shop, $request);

        return $this->handle($offer);
    }


}
