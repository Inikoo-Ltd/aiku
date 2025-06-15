<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 25 Jan 2024 16:42:23 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RentalAgreement\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Fulfilment\RentalAgreement;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class RentalAgreementHydrateSnapShots implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(RentalAgreement $rentalAgreement): string
    {
        return $rentalAgreement->id;
    }

    public function handle(RentalAgreement $rentalAgreement): void
    {
        $stats = [
            'number_rental_agreement_snapshots' => $rentalAgreement->snapshots()->count()
        ];


        $rentalAgreement->stats->update($stats);

    }
}
