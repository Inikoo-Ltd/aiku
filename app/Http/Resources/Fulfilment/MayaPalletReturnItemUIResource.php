<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Feb 2025 13:57:29 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $reference
 * @property mixed $customer_reference
 * @property mixed $fulfilment_customer_id
 * @property mixed $slug
 * @property mixed $notes
 * @property mixed $state
 * @property mixed $type
 * @property mixed $pivot
 * @property mixed $status
 * @property mixed $location_slug
 * @property mixed $location_code
 * @property mixed $location_id
 * @property mixed $warehouse_id
 * @property mixed $pallet_delivery_id
 * @property mixed $pallet_return_id
 * @property mixed $pallet_id
 * @property mixed $fulfilment_customer_name
 * @property mixed $fulfilment_customer_slug
 */
class MayaPalletReturnItemUIResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'pallet_id' => $this->pallet->id,
            'slug' => $this->pallet->slug,
            'reference' => $this->pallet->reference,
            'customer_reference' => (string) $this->pallet->customer_reference,
            'fulfilment_customer_name' => $this->pallet->fulfilment_customer_name,
            'fulfilment_customer_slug' => $this->pallet->fulfilment_customer_slug,
            'fulfilment_customer_id' => $this->pallet->fulfilment_customer_id,
            'notes' => (string) $this->pallet->notes,
            'state' => $this->pallet->state->value,
            'type_icon' => $this->pallet->type->typeIcon()[$this->pallet->type->value],
            'type' => $this->pallet->type,
            'state_label' => PalletStateEnum::labels()[$this->pallet->state->value],
            'state_icon' => PalletStateEnum::stateIcon()[$this->pallet->state->value],
            'status' => $this->pallet->status,
            'status_label' => $this->pallet->status->labels()[$this->pallet->status->value],
            'status_icon' => $this->pallet->status->statusIcon()[$this->pallet->status->value],
            'location' => $this->location_slug,
            'location_code' => $this->location_code,
            'stored_items' => $this->pallet->storedItems->map(fn ($storedItem) => [
                'reference' => $storedItem->reference,
                'quantity' => (int) $storedItem->pivot->quantity,
            ]),
            'stored_items_quantity' => (int) $this->pallet->storedItems()->sum('quantity'),
            'pallet_return' => PalletReturnResource::make($this->palletReturn),
        ];
    }
}
