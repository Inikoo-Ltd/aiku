<?php

/*
 * author Arya Permana - Kirin
 * created on 10-01-2025-15h-08m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Catalogue;

use App\Models\Fulfilment\RentalAgreement;
use Illuminate\Http\Resources\Json\JsonResource;

class RetinaRentalAgreementResource extends JsonResource
{
    public static $wrap = null;

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
        ];
    }
}
