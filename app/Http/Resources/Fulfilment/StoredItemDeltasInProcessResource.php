<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Feb 2024 11:07:20 Malaysia Time, Bali Airport, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Models\Fulfilment\Pallet;
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
class StoredItemDeltasInProcessResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Pallet $pallet */
        $pallet = $this->resource;

        return [
            'stored_item_audit_id' => $this->stored_item_audit_id,
            'id' => $this->id,
            'slug' => $this->slug,
            'reference' => $this->reference,
            'customer_reference' => (string) $this->customer_reference,

            'location_slug' => $this->location_slug,
            'location_code' => $this->location_code,
            'location_id' => $this->location_id,

            'warehouse_slug' => $this->warehouse_slug,

            'stored_items' => $pallet->getEditStoredItemDeltasQuery($this->id, $this->stored_item_audit_id)
                ->where('pallet_stored_items.pallet_id', $this->id)
                ->get()->map(fn ($item) => [
                    'stored_item_audit_id' => $this->stored_item_audit_id,
                    'pallet_id' => $item->pallet_id,
                    'stored_item_id' => $item->stored_item_id,
                    'reference' => $item->stored_item_reference,
                    'quantity' => (int) $item->quantity,
                    'audited_quantity' => (int) $item->audited_quantity,
                    'audit_notes' => $item->audit_notes,
                    'stored_item_audit_delta_id' => $item->stored_item_audit_delta_id,
                    'audit_type' => $item->audit_type,
                    'update_routes' => [
                        'name' => 'grp.models.stored_item_audit_delta.update',
                        'parameters' => [
                            $item->stored_item_audit_delta_id,
                        ],
                    ],
                    'type' => 'current_item',
                ]),

            'new_stored_items' => $pallet->getEditNewStoredItemDeltasQuery($this->id)
                ->where('stored_item_audit_deltas.pallet_id', $this->id)
                ->where('stored_item_audit_deltas.stored_item_audit_id', $this->stored_item_audit_id)
                ->get()->map(fn ($item) => [
                    'stored_item_audit_id' => $this->stored_item_audit_id,
                    'stored_item_id' => $item->stored_item_id,
                    'reference' => $item->stored_item_reference,
                    'quantity' => 0,
                    'audited_quantity' => (int) $item->audited_quantity,
                    'stored_item_audit_delta_id' => $item->audit_id,
                    'audit_type' => $item->audit_type,
                    'update_routes' => [
                        'name' => 'grp.models.stored_item_audit_delta.update',
                        'parameters' => [
                            $item->audit_id,
                        ],
                    ],
                    'audit_notes' => $item->audit_notes,
                    'type' => 'new_item',
                ]),

            // use routes in
            //
            //            'auditRoute'           => [
            //                'name'       => 'grp.models.pallet.stored-items.audit',
            //                'parameters' => [
            //                    $this->id,
            //                    $this->stored_item_audit_id
            //
            //                ]
            //            ],
            //            'resetAuditRoute'      => [
            //                'name'       => 'grp.models.pallet.stored-items.audit.reset',
            //                'parameters' => [$this->id]
            //            ],
            //            'storeStoredItemRoute' => [
            //                'name'       => 'grp.models.pallet.stored-items.update',
            //                'parameters' => [$this->id]
            //            ],
        ];
    }
}
