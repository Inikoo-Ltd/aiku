<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Feb 2025 12:57:53 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Models\Fulfilment\PalletReturnItem;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 *                     *@property mixed $quantity_ordered
 * @property mixed $quantity_dispatched
 * @property mixed $quantity_fail
 * @property mixed $quantity_cancelled
 * @property mixed $state
 * @property mixed $location_code
 * @property mixed $location_id
 * @property mixed $location_slug
 * @property mixed $stored_items_reference
 * @property mixed $stored_items_id
 * @property mixed $stored_items_slug
 * @property mixed $stored_items_name
 * @property mixed $pallets_reference
 * @property mixed $pallets_customer_reference
 * @property mixed $pallets_id
 * @property mixed $pallets_slug
 */
class PalletReturnItemResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var PalletReturnItem $palletReturnItem */
        $palletReturnItem = $this;

        return [
            'id' => $palletReturnItem->id,
            'quantity_ordered' => $palletReturnItem->quantity_ordered,
            'quantity_dispatched' => $palletReturnItem->quantity_dispatched,
            'quantity_picked' => $palletReturnItem->quantity_picked,
            'quantity_fail' => $palletReturnItem->quantity_fail,
            'quantity_cancelled' => $palletReturnItem->quantity_cancelled,
            'state' => $palletReturnItem->state,
            'state_icon' => $palletReturnItem->state->stateIcon()[$this->state->value],

            'location_code' => $palletReturnItem->pickingLocation?->code,
            'location_id' => $palletReturnItem->pickingLocation?->id,
            'location_slug' => $palletReturnItem->pickingLocation?->slug,

            'stored_items_reference' => $palletReturnItem->storedItem?->reference,
            'stored_items_id' => $palletReturnItem->storedItem?->id,
            'stored_items_slug' => $palletReturnItem->storedItem?->slug,
            'stored_items_name' => $palletReturnItem->storedItem?->name,

            'pallets_reference' => $palletReturnItem->pallet?->reference,
            'pallets_customer_reference' => $palletReturnItem->pallet?->customer_reference,
            'pallets_id' => $palletReturnItem->pallet?->id,
            'pallets_slug' => $palletReturnItem->pallet?->slug,

        ];
    }
}
