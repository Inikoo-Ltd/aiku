<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Jun 2025 14:00:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsStateHandling;
use App\Http\Resources\Inventory\LocationOrgStocksForPickingActionsResource;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

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
            'items' => DeliveryNoteItemsStateHandlingResource::collection(IndexDeliveryNoteItemsStateHandling::run($deliveryNote))->resolve()
        ];
    }
}
