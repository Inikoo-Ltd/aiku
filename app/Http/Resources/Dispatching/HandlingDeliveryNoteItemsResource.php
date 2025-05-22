<?php
/*
 * author Arya Permana - Kirin
 * created on 22-05-2025-11h-14m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Dispatching;

use App\Models\Dispatching\DeliveryNoteItem;
use Illuminate\Http\Resources\Json\JsonResource;

class HandlingDeliveryNoteItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        $deliveryNoteItem = DeliveryNoteItem::find($this->id);
        // dd($deliveryNoteItem);
        return [
            'id'                  => $this->id,
            'state'               => $this->state,
            'state_icon'          => $this->state->stateIcon()[$this->state->value],
            'quantity_required'   => intVal($this->quantity_required),
            'quantity_picked'     => intVal($this->quantity_picked),
            'quantity_packed'     => intVal($this->quantity_packed),
            'org_stock_code'      => $this->org_stock_code,
            'org_stock_name'      => $this->org_stock_name,
            'pickings'            => $deliveryNoteItem->pickings ? PickingsResource::collection($deliveryNoteItem->pickings) : [],
            'packings'            => $deliveryNoteItem->packings ? PackingsResource::collection($deliveryNoteItem->packings) : []
        ];
    }
}
