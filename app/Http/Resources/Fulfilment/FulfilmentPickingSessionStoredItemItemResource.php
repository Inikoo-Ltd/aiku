<?php

namespace App\Http\Resources\Fulfilment;

use Illuminate\Http\Resources\Json\JsonResource;

class FulfilmentPickingSessionStoredItemItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'pallet_return_reference' => $this->pallet_return_reference ?? null,
            'pallet_return_slug'     => $this->pallet_return_slug ?? $this->palletReturn?->slug ?? null,
            'pallet_return_type'     => $this->pallet_return_type ?? $this->palletReturn?->type?->value ?? null,
            'stored_item_reference'  => $this->stored_item_reference ?? $this->storedItem?->reference,
            'pallet_reference'       => $this->pallet_reference ?? $this->pallet?->reference,
            'location'               => $this->pickingLocation?->slug ?? null,
            'location_code'          => $this->pickingLocation?->code ?? null,
            'quantity_ordered'       => (int) ($this->quantity_ordered ?? 0),
            'quantity_picked'        => (int) ($this->quantity_picked ?? 0),
            'state'                  => $this->state?->value ?? null,
            'state_icon'             => $this->state?->stateIcon()[$this->state?->value] ?? null,
            'updateRoute'            => [
                'name'       => 'grp.models.pallet-return-item.pick',
                'parameters' => [
                    'palletReturnItem' => $this->id
                ],
                'method'     => 'patch'
            ],
            'undoRoute'              => [
                'name'       => 'grp.models.pallet-return-item.undo-picking-stored-item',
                'parameters' => [
                    'palletReturnItem' => $this->id
                ],
                'method'     => 'patch'
            ],
        ];
    }
}
