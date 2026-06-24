<?php

namespace App\Http\Resources\Fulfilment;

use Illuminate\Http\Resources\Json\JsonResource;

class FulfilmentPickingSessionsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'slug'                  => $this->slug,
            'reference'             => $this->reference,
            'state'                 => $this->state,
            'state_icon'            => $this->state->stateIcon()[$this->state->value],
            'start_at'              => $this->start_at,
            'end_at'                => $this->end_at,
            'number_pallet_returns' => $this->number_pallet_returns,
            'number_items'          => $this->number_items,
            'user_id'               => $this->user_id,
            'user_username'         => $this->user_username,
            'user_name'             => $this->user_name,
            'quantity_picked'       => $this->quantity_picked,
            'quantity_packed'       => $this->quantity_packed,
            'picking_percentage'    => $this->picking_percentage.'%',
            'packing_percentage'    => $this->packing_percentage.'%',
        ];
    }
}
