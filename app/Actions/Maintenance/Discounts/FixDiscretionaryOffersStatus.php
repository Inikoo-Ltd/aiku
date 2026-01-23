<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 Jan 2026 13:11:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Discounts;

use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Models\Discounts\Offer;
use Lorisleiva\Actions\Concerns\AsAction;

class FixDiscretionaryOffersStatus
{
    use AsAction;
    use WithOrganisationSource;

    public string $commandSignature = 'repair:discretionary_offers_status';

    /**
     * @throws \Exception
     */
    public function asCommand(): void
    {
        $offers = Offer::where('type', 'Discretionary')->get();


        /** @var Offer $offer */
        foreach ($offers as $offer) {
            $offer->update([
                'status'   => true,
                'state'    => OfferStateEnum::ACTIVE,
                'duration' => OfferDurationEnum::PERMANENT
            ]);

            foreach ($offer->offerAllowances as $offerAllowance) {
                $offerAllowance->update([
                    'state'    => $offer->state->value,
                    'status'   => $offer->status,
                    'duration' => OfferDurationEnum::PERMANENT
                ]);
            }
        }
    }
}
