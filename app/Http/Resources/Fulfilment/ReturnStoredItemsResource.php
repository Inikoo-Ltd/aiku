<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Jul 2023 13:44:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $reference
 * @property string $state
 * @property string $status
 * @property string $notes
 * @property \App\Models\CRM\Customer $customer
 * @property \App\Models\Inventory\Location $location
 */
class ReturnStoredItemsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $storedItem = $this;

        return [
            'id' => $storedItem->id,
            'pallet_id' => $storedItem->pallet_id,
            'pallet_slug' => $storedItem->pallet_slug,
            'pallet_reference' => $storedItem->pallet_reference ?? '',
            'stored_item_id' => $storedItem->stored_item_id,
            'stored_item_reference' => $storedItem->stored_item_reference,
            'stored_item_slug' => $storedItem->stored_item_slug,
            'state_icon' => StoredItemStateEnum::from($storedItem->stored_item_state)->stateIcon()[$storedItem->stored_item_state],
            'stored_item_state' => $storedItem->stored_item_state,
            'quantity' => (int) $storedItem->quantity,
            'damaged_quantity' => $storedItem->damaged_quantity,
            'location' => $storedItem->location_code,
        ];
    }
}
