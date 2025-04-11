<?php

/*
 * author Arya Permana - Kirin
 * created on 03-04-2025-13h-07m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Fulfilment;

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
class StoredItemsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var \App\Models\Fulfilment\StoredItem $storedItem */
        $storedItem = $this;

        return [
            'id'             => $storedItem->id,
            'reference'      => $storedItem->reference,
            'slug'           => $storedItem->slug,
            'state'          => $storedItem->state,
            'name'           => $storedItem->name,
            'total_quantity' => $storedItem->total_quantity,
        ];
    }
}
