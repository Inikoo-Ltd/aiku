<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-11h-54m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Fulfilment;

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Http\Resources\Inventory\LocationResource;
use App\Models\Fulfilment\Pallet;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $customer_reference
 * @property string $state
 * @property string $status
 * @property string $notes
 * @property \App\Models\Fulfilment\FulfilmentCustomer $fulfilmentCustomer
 * @property \App\Models\Inventory\Location $location
 * @property \App\Models\Inventory\Warehouse $warehouse
 * @property \App\Models\Fulfilment\StoredItem $storedItems
 */
class RetinaPalletResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Pallet $pallet */
        $pallet = $this;

        $timeline = [];
        foreach (PalletStateEnum::cases() as $state) {
            $timeline[] = [
                'label' => $state->labels()[$state->value],
                'tooltip' => $state->labels()[$state->value],
                'key' => $state->value,
                'icon' => $state->stateIcon()[$state->value]['icon'],
                'current' => $state == $this->state,
                'timestamp' => $this->{$state->snake().'_at'} ? $this->{$state->snake().'_at'}->toISOString() : null,
            ];
        }

        return [
            'id' => $this->id,
            'reference' => $pallet->reference,
            'customer_reference' => $pallet->customer_reference,
            'slug' => $pallet->slug ?? null,
            'customer' => [
                'name' => $this->fulfilmentCustomer->customer->name,
                'contact_name' => $this->fulfilmentCustomer->customer->contact_name,
            ],
            'location' => LocationResource::make($this->location),
            'state' => $this->state,
            'status' => $this->status,
            'notes' => $this->notes ?? '',
            'rental_id' => $this->rental_id,
            'status_label' => $pallet->status->labels()[$pallet->status->value],
            'status_icon' => $pallet->status->statusIcon()[$pallet->status->value],
            'items' => StoredItemResource::collection($this->storedItems ?? []),
            'timeline' => $timeline,
        ];
    }
}
