<?php

/*
 * author Arya Permana - Kirin
 * created on 21-02-2025-15h-04m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Fulfilment;

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
 * @property int $stored_item_audit_id
 */
class NewStoredItemDeltasInProcessForPalletResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'stored_item_audit_id' => $this->stored_item_audit_id,
            'id' => $this->id,
            'slug' => $this->slug,
            'reference' => $this->reference,
            'name' => (string) $this->name,
            'audit_notes' => $this->audit_notes,
            'audited_quantity' => $this->audited_quantity,
            'delta_state' => $this->delta_state,
            'audit_type' => $this->audit_type,
            'stored_item_audit_delta_id' => $this->stored_item_audit_delta_id,
            'update_routes' => [
                'name' => 'grp.models.stored_item_audit_delta.update',
                'parameters' => [
                    $this->stored_item_audit_delta_id,
                ],
            ],
        ];
    }
}
