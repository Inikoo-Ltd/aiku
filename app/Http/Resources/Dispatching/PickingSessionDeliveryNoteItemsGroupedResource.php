<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Jun 2025 14:00:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsStateHandling;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Http\Resources\Json\JsonResource;

class PickingSessionDeliveryNoteItemsGroupedResource extends JsonResource
{
    public function toArray($request): array
    {
        $deliveryNote = DeliveryNote::find($this->delivery_note_id);
        return [
            'delivery_note_reference'      => $this->delivery_note_reference,
            'delivery_note_state_icon'     => $deliveryNote->state->stateIcon()[$deliveryNote->state->value],
            'delivery_note_slug'           => $this->delivery_note_slug,
            'delivery_note_id'             => $this->delivery_note_id,
            'delivery_note_state'          => $deliveryNote->state,

            'delivery_note_customer_notes'   => $this->delivery_note_customer_notes,
            'delivery_note_public_notes'     => $this->delivery_note_public_notes,
            'delivery_note_internal_notes'   => $this->delivery_note_internal_notes,
            'delivery_note_shipping_notes'   => $this->delivery_note_shipping_notes,

            'items' => DeliveryNoteItemsStateHandlingResource::collection(IndexDeliveryNoteItemsStateHandling::run($deliveryNote))->resolve()
        ];
    }
}
