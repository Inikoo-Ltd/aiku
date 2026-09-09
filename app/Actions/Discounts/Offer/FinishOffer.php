<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 May 2026 21:34:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Comms\Email\SendFinishOfferEmailToSubscribers;
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

    public function handle(Offer $offer, bool $notifySubscribers = true): Offer
    {
        if ($offer->state == OfferStateEnum::FINISHED && !$offer->status) {
            return $offer;
        }

        $currentStatus = $offer->status;
        $endAt         = $offer->end_at && $offer->end_at->lte(now()) ? $offer->end_at : now();

        $offer->update(
            [
                'state'  => OfferStateEnum::FINISHED,
                'end_at' => $endAt
            ]
        );

        foreach ($offer->offerAllowances as $offerAllowance) {
            $offerAllowance->update([
                'state'  => OfferAllowanceStateEnum::FINISHED,
                'end_at' => $endAt
            ]);
        }
        if ($currentStatus != $offer->status) {
            $this->handleOfferSideEffects($offer);
        }

        if ($notifySubscribers) {
            SendFinishOfferEmailToSubscribers::dispatch($offer->id)->delay(now()->addSeconds(10));
        }

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
