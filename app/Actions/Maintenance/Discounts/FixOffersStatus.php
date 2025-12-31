<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 31 Dec 2025 17:20:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Discounts;

use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Models\Discounts\Offer;
use Lorisleiva\Actions\Concerns\AsAction;

class FixOffersStatus
{
    use AsAction;
    use WithOrganisationSource;

    public string $commandSignature = 'repair:offers_status';

    /**
     * @throws \Exception
     */
    public function asCommand(): void
    {
        $offers = Offer::all();


        /** @var Offer $offer */
        foreach ($offers as $offer) {
            if ($offer->state != OfferStateEnum::ACTIVE) {
                $offer->update([
                    'status' => false,
                ]);

                foreach ($offer->offerAllowances as $offerAllowance) {
                    $offerAllowance->update([
                        'state'  => $offer->state->value,
                        'status' => $offer->status,
                    ]);
                }
            }else{
                $offer->update([
                    'status' => true,
                ]);
            }
        }
    }


}
