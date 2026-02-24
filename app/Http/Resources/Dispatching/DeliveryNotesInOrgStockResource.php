<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Feb 2026 15:03:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $reference
 * @property mixed $date
 * @property mixed $delivery_note_date
 * @property mixed $state
 * @property mixed $quantity_required
 * @property mixed $packed_in
 * @property mixed $quantity_dispatched
 * @property mixed $quantity_picked
 * @property mixed $quantity_packed
 */
class DeliveryNotesInOrgStockResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        $requiredFactionalData = riseDivisor(
            divideWithRemainder(
                findSmallestFactors(
                    $this->quantity_required
                )
            ),
            $this->packed_in
        );


        $packedIn = $this->packed_in;
        if ($packedIn == null) {
            $packedIn = 1;
        }


        $quantityDispatched = $this->quantity_dispatched;
        if ($quantityDispatched == null) {
            $quantityDispatched = 0;
        }


        return [
            'id'                             => $this->id,
            'slug'                           => $this->slug,
            'reference'                      => $this->reference,
            'date'                           => $this->date ?? $this->delivery_note_date,
            'state'                          => $this->state,
            'state_icon'                     => $this->state->stateIcon()[$this->state->value],
            'quantity_required'              => $this->quantity_required,
            'quantity_required_fractional'   => $requiredFactionalData,
            'quantity_dispatched'            => $this->quantity_dispatched,
            'quantity_dispatched_fractional' => riseDivisor(divideWithRemainder(findSmallestFactors($quantityDispatched)), $packedIn),
            'quantity_picked'                => $this->quantity_picked,
            'quantity_picked_fractional'     => riseDivisor(divideWithRemainder(findSmallestFactors($this->quantity_picked ?? 0)), $packedIn),
            'quantity_packed'                => $this->quantity_packed,
            'quantity_packed_fractional'     => riseDivisor(divideWithRemainder(findSmallestFactors($this->quantity_packed ?? 0)), $packedIn),


        ];
    }
}
