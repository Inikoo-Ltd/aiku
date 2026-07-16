<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Jun 2025 14:00:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use App\Actions\Dispatching\DeliveryNote\WithDeliveryNoteLeaflets;
use App\Actions\Dispatching\DeliveryNote\WithDeliveryNotePackaging;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsStateHandling;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $delivery_note_id
 * @property mixed $delivery_note_slug
 * @property mixed $delivery_note_customer_notes
 * @property mixed $delivery_note_public_notes
 * @property mixed $delivery_note_internal_notes
 * @property mixed $delivery_note_shipping_notes
 * @property mixed $delivery_note_reference
 * @property mixed $packed_in
 * @property mixed $delivery_note_is_premium_dispatch
 * @property mixed $delivery_note_has_extra_packing
 */
class PickingSessionDeliveryNoteItemsGroupedResource extends JsonResource
{
    use WithDeliveryNotePackaging;
    use WithDeliveryNoteLeaflets;

    public function toArray($request): array
    {
        $deliveryNote = DeliveryNote::find($this->delivery_note_id);
        $packaging    = $this->effectivePackaging($deliveryNote);

        return [
            'id'                              => $this->delivery_note_id,
            'delivery_note_reference'         => $this->delivery_note_reference,
            'delivery_note_state_icon'        => $deliveryNote->state->stateIcon()[$deliveryNote->state->value],
            'delivery_note_slug'              => $this->delivery_note_slug,
            'delivery_note_id'                => $this->delivery_note_id,
            'delivery_note_state'             => $deliveryNote->state,
            'delivery_note_is_for_collection' => (bool)$deliveryNote->collection_address_id,
            'delivery_note_has_waiting_items' => $deliveryNote->deliveryNoteItems()
                ->where(function (Builder $query) {
                    $query->where('has_waiting_warehouse', true)
                        ->orWhere('has_waiting_crm', true);
                })
                ->exists(),

            'delivery_note_customer_notes' => $this->delivery_note_customer_notes,
            'delivery_note_public_notes'   => $this->delivery_note_public_notes,
            'delivery_note_internal_notes' => $this->delivery_note_internal_notes,
            'delivery_note_shipping_notes' => $this->delivery_note_shipping_notes,

            'delivery_note_is_premium_dispatch' => $this->delivery_note_is_premium_dispatch,
            'delivery_note_has_extra_packing'   => $this->delivery_note_has_extra_packing,

            'packaging'         => $this->getPackaging($packaging),
            'packaging_options' => $this->getPackagingOptions($deliveryNote, $packaging?->family_code),
            'leaflets'          => $this->getLeaflets($deliveryNote),
            'print_status'      => $this->getPrintStatus($deliveryNote),

            'items' => DeliveryNoteItemsStateHandlingResource::collection(IndexDeliveryNoteItemsStateHandling::run($deliveryNote, ignoreParentPagination: true))->resolve()
        ];
    }

}
