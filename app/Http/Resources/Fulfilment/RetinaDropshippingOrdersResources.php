<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-11h-54m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Fulfilment;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $customer_reference
 * @property \App\Enums\Dropshipping\ChannelFulfilmentStateEnum $state
 * @property string $status
 * @property string $notes
 * @property \App\Models\Fulfilment\FulfilmentCustomer $fulfilmentCustomer
 * @property \App\Models\Inventory\Location $location
 * @property \App\Models\Inventory\Warehouse $warehouse
 * @property \App\Models\Fulfilment\StoredItem $storedItems
 */
class RetinaDropshippingOrdersResources extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'date'              => $this->date,
            'name'              => $this->name,
            'reference' => $this->reference,
            'slug' => $this->slug,
            'client_name' => $this->customerClient?->contact_name,
            'state' => $this->state,
            'number_transactions' => $this->number_transactions,
            'state_label'           => $this->state->labels()[$this->state->value],
            'state_icon'            => $this->state->stateIcon()[$this->state->value]
        ];
    }
}
