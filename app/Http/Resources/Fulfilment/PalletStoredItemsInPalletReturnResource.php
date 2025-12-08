<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Feb 2025 17:18:03 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

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
class PalletStoredItemsInPalletReturnResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'quantity_ordered' => $this->quantity_ordered,
            'quantity_dispatched' => $this->quantity_dispatched,
            'quantity_fail' => $this->quantity_fail,
            'quantity_cancelled' => $this->quantity_cancelled,
            'quantity_picked' => $this->quantity_picked,

            'state' => $this->state,
            'state_icon' => $this->state->stateIcon()[$this->state->value],
            'location_code' => $this->location_code,
            'location_id' => $this->location_id,
            'location_slug' => $this->location_slug,

            'stored_items_reference' => $this->stored_items_reference,
            'stored_items_id' => $this->stored_items_id,
            'stored_items_slug' => $this->stored_items_slug,
            'stored_items_name' => $this->stored_items_name,

            'pallets_reference' => $this->pallets_reference,
            'pallets_customer_reference' => $this->pallets_customer_reference,
            'pallets_id' => $this->pallets_id,
            'pallets_slug' => $this->pallets_slug,

        ];
    }
}
