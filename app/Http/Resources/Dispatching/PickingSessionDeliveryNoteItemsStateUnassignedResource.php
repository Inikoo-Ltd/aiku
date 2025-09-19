<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Jun 2025 15:37:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
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
 * @property mixed $org_stock_slug
 * @property mixed $delivery_note_reference
 * @property mixed $delivery_note_slug
 * @property mixed $packed_in
 */
class PickingSessionDeliveryNoteItemsStateUnassignedResource extends JsonResource
{
    public function toArray($request): array
    {
        $requiredFactionalData = riseDivisor(
            divideWithRemainder(
                findSmallestFactors($this->quantity_required)
            ),
            $this->packed_in
        );

        return [
            'id'                           => $this->id,
            'state'                        => $this->state,
            'state_icon'                   => $this->state->stateIcon()[$this->state->value],
            'quantity_required'            => $this->quantity_required,
            'quantity_required_fractional' => $requiredFactionalData,
            'org_stock_slug'               => $this->org_stock_slug,
            'org_stock_code'               => $this->org_stock_code,
            'org_stock_name'               => $this->org_stock_name,
            'delivery_note_reference'      => $this->delivery_note_reference,
            'delivery_note_slug'           => $this->delivery_note_slug,
            'delivery_note_is_premium_dispatch'          => $this->delivery_note_is_premium_dispatch,
            'delivery_note_has_extra_packing'            => $this->delivery_note_has_extra_packing,
            
            'delivery_note_customer_notes'   => $this->delivery_note_customer_notes,
            'delivery_note_public_notes'     => $this->delivery_note_public_notes,
            'delivery_note_internal_notes'   => $this->delivery_note_internal_notes,
            'delivery_note_shipping_notes'   => $this->delivery_note_shipping_notes,


        ];
    }
}
