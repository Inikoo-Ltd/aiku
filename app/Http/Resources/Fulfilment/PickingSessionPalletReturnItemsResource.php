<?php

namespace App\Http\Resources\Fulfilment;

use Illuminate\Http\Resources\Json\JsonResource;

class PickingSessionPalletReturnItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'state'               => $this->state,
            'state_icon'          => $this->state->stateIcon()[$this->state->value],
            'quantity_ordered'    => $this->quantity_ordered,
            'quantity_picked'     => $this->quantity_picked,
            'quantity_dispatched' => $this->quantity_dispatched,
            'stored_item_code'    => $this->stored_item_code,
            'stored_item_name'    => $this->stored_item_name,
            'pallet_return_reference' => $this->pallet_return_reference,
        ];
    }
}
