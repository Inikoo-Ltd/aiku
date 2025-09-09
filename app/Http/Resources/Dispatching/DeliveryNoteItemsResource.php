<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Feb 2023 22:40:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $org_stock_id
 * @property mixed $id
 * @property mixed $state
 * @property mixed $quantity_required
 * @property mixed $quantity_picked
 * @property mixed $org_stock_code
 * @property mixed $org_stock_name
 * @property mixed $is_handled
 * @property mixed $quantity_packed
 * @property mixed $quantity_not_picked
 * @property mixed $quantity_dispatched
 * @property mixed $packed_in
 */
class DeliveryNoteItemsResource extends JsonResource
{
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



        return [
            'id'                           => $this->id,
            'state'                        => $this->state,
            'state_icon'                   => $this->state->stateIcon()[$this->state->value],
            'quantity_required'            => $this->quantity_required,
            'quantity_required_fractional' => $requiredFactionalData,

            'quantity_dispatched'          => $this->quantity_dispatched,
            'quantity_dispatched_fractional'   => riseDivisor(divideWithRemainder(findSmallestFactors($this->quantity_dispatched)), $this->packed_in),
            'quantity_picked'              => $this->quantity_picked,
            'quantity_picked_fractional'   => riseDivisor(divideWithRemainder(findSmallestFactors($this->quantity_picked)), $this->packed_in),

            'quantity_packed'              => $this->quantity_packed,
            'quantity_packed_fractional'   => riseDivisor(divideWithRemainder(findSmallestFactors($this->quantity_packed)), $this->packed_in),


            'org_stock_code'               => $this->org_stock_code,
            'org_stock_name'               => $this->org_stock_name,
            'org_stock_slug'               => $this->org_stock_slug,
        ];
    }
}
