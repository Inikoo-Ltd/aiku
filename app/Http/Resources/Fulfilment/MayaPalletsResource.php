<?php
/*
 * author Arya Permana - Kirin
 * created on 25-03-2025-14h-33m
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
class MayaPalletsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Pallet $pallet */
        $pallet = $this;
        return [
            'id'                    => $this->id,
            'reference'             => $pallet->reference,
            'customer_reference'    => $pallet->customer_reference,
            'slug'                  => $pallet->slug ?? null,
            'state'                 => $this->state,
            'status'                => $this->status,
            'notes'                 => $this->notes ?? '',
            'rental_id'             => $this->rental_id,
            'status_label'          => $pallet->status->labels()[$pallet->status->value],
            'state_icon'           => $pallet->state->stateIcon()[$pallet->state->value],
            'status_icon'           => $pallet->status->statusIcon()[$pallet->status->value],
        ];
    }
}
