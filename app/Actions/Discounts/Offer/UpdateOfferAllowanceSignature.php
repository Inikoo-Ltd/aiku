<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Nov 2025 15:20:57 Malaysia Time, Pantai Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\OrgAction;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Models\Discounts\Offer;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class UpdateOfferAllowanceSignature extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Offer $offer): Offer
    {
        $allowanceSignature = '';

        /** @var \App\Models\Discounts\OfferAllowance $offerAllowance */
        foreach ($offer->offerAllowances()->where('status', true)->get() as $offerAllowance) {
            if ($allowanceSignature != '') {
                $allowanceSignature .= '|';
            }

            if ($offerAllowance->target_type) {
                $allowanceSignature .= $offerAllowance->target_type->value.':';
            }
            if ($offerAllowance->type) {
                $allowanceSignature .= $offerAllowance->type->value.':';
            }

            if ($offerAllowance->type == OfferAllowanceType::PERCENTAGE_OFF) {
                $allowanceSignature .= Arr::get($offerAllowance->data, 'percentage_off', 'error');
            }
        }

        $offer->update(['allowance_signature' => $allowanceSignature]);


        return $offer;
    }

    public function getCommandSignature(): string
    {
        return 'offer:update_allowance_signature {offer?}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        if ($command->argument('offer')) {
            $offer = Offer::where('id', $command->argument('offer'))->firstOrFail();
            $this->handle($offer);

            return 0;
        }

        foreach (Offer::all() as $offer) {
            $this->handle($offer);
        }


        return 0;
    }

}
