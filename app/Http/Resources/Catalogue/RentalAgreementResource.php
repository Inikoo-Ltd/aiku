<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 18 Apr 2024 09:27:56 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Models\Fulfilment\RentalAgreement;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalAgreementResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var RentalAgreement $rentalAgreement */
        $rentalAgreement = $this;

        return [
            'id' => $rentalAgreement->id,
            'slug' => $rentalAgreement->slug,
            'reference' => $rentalAgreement->reference,
            'state' => $rentalAgreement->state,
            'billing_cycle' => $rentalAgreement->billing_cycle,
            'pallets_limit' => $rentalAgreement->pallets_limit,
            'route' => [
                'name' => 'grp.org.fulfilments.show.crm.customers.show.rental-agreement.edit',
                'parameters' => array_values($request->route()->originalParameters()),
            ],
        ];
    }
}
