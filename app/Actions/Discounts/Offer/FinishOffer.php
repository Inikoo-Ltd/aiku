<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 May 2026 21:34:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Discounts\Offer\Traits\HandlesOfferSideEffects;
use App\Actions\OrgAction;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Models\Discounts\Offer;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class FinishOffer extends OrgAction
{
    use AsAction;
    use HandlesOfferSideEffects;

    public function handle(Offer $offer): Offer
    {
        if ($offer->state == OfferStateEnum::FINISHED) {
            return $offer;
        }

        $currentStatus = $offer->status;

        $offer->update(
            [
                'state'  => OfferStateEnum::FINISHED,
                'status' => false,
                'end_at' => now()
            ]
        );

        foreach ($offer->offerAllowances as $offerAllowance) {
            $offerAllowance->update([
                'state'  => OfferAllowanceStateEnum::FINISHED,
                'status' => false,
                'end_at' => now()
            ]);
        }

        $this->handleOfferSideEffects($offer, $currentStatus != $offer->status);

        return $offer;
    }

    public function asController(Offer $offer, ActionRequest $request): void
    {
        $this->initialisationFromShop($offer->shop, $request);
        $this->handle($offer);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
