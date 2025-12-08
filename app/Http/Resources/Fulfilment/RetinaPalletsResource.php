<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 19 Jan 2025 14:54:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Models\Fulfilment\StoredItem;
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
 * @property mixed $storedItems
 * @property mixed $rental_id
 * @property mixed $status
 * @property mixed $location_slug
 * @property mixed $location_code
 * @property mixed $location_id
 * @property mixed $warehouse_id
 * @property mixed $pallet_delivery_id
 * @property mixed $pallet_return_id
 * @property mixed $fulfilment_customer_name
 * @property mixed $fulfilment_customer_slug
 * @property mixed $rental_code
 * @property mixed $rental_name
 */
class RetinaPalletsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'reference' => $this->reference,
            'customer_reference' => $this->customer_reference,
            'notes' => (string) $this->notes,
            'type_icon' => $this->type->typeIcon()[$this->type->value],
            'type' => $this->type,
            'rental_code' => $this->rental_code,
            'rental_name' => $this->rental_name,
            'location_code' => $this->location_code,
            'status' => $this->status,
            'status_label' => $this->status->labels()[$this->status->value],
            'status_icon' => $this->status->statusIcon()[$this->status->value],

            'incident_report_message' => $this->incident_report->message ?? '-',
            'stored_items' => $this->storedItems->map(fn (StoredItem $storedItem) => [
                'id' => $storedItem->id,
                'reference' => $storedItem->reference,
                'notes' => $storedItem->notes,
                'state' => $storedItem->state,
                'state_icon' => $storedItem->state->stateIcon()[$storedItem->state->value],
                'quantity' => (int) $storedItem->pivot->quantity,
            ]),
            'stored_items_quantity' => (int) $this->storedItems()->sum('quantity'),
            'updateRoute' => [
                'name' => 'retina.models.pallet.update',
                'parameters' => $this->id,
            ],
            'deleteRoute' => [
                'name' => 'retina.models.pallet.delete',
                'parameters' => $this->id,
            ],
            'deleteFromDeliveryRoute' => [
                'name' => 'retina.models.pallet-delivery.pallet.delete',
                'parameters' => [$this->pallet_delivery_id, $this->id],
            ],
            'deleteFromReturnRoute' => [
                'name' => 'retina.models.pallet-return.pallet.delete',
                'parameters' => [$this->pallet_return_id, $this->id],
            ],
            'storeStoredItemRoute' => [
                'name' => 'retina.models.pallet.stored-items.update',
                'parameters' => [$this->id],
            ],
        ];
    }
}
