<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Jul 2023 13:44:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Http\Resources\HasSelfCall;
use App\Models\Fulfilment\Pallet;
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
class StoredItemResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var \App\Models\Fulfilment\StoredItem $storedItem */
        $storedItem = $this;

        return [
            'id'             => $storedItem->id,
            'created_at'     => $storedItem->created_at,
            'reference'      => $storedItem->reference,
            'slug'           => $storedItem->slug,
            'audit_slug'     => $storedItem->audit_slug,
            'customer_name'  => $storedItem->fulfilmentCustomer?->customer['name'],
            'organisation_name'  => $storedItem->organisation?->name,
            'location'       => $storedItem->location ? $storedItem->location['slug'] : '-',
            'state'          => $storedItem->state,
            'number_pallets' => $storedItem->number_pallets,
            'no_price'          => true,
            'no_code'           => true,
            'number_audits' => $storedItem->number_audits,
            'notes'          => $storedItem->notes ?? '-',
            'status'         => $storedItem->status,
            'state_icon'     => $storedItem->state->stateIcon()[$storedItem->state->value],
            'quantity'       => (int) $storedItem->pallets?->sum('pivot.quantity'),
            'total_quantity' => $storedItem->pallets?->sum('pivot.quantity'),
            'name'          => $storedItem->name,
            'max_quantity'   => $storedItem->pallets?->sum('pivot.quantity'),
            'last_audit_at'  => $storedItem->last_audit_at,
            'last_stored_item_audit_id' => $storedItem->last_stored_item_audit_id,

            'pallets'                   => $storedItem->pallets->map(fn (Pallet $pallet) => [
                'id'                    => $pallet->id,
                'reference'             => $pallet->reference ?? __('To be delivered'),
                'quantity_stored_item'  => $pallet->storedItems->count(),
                'customer_reference'    => $pallet->customer_reference,
                'state'                 => $pallet->state,
                'state_icon'            => $pallet->state->stateIcon()[$pallet->state->value],
            ]),

            'deleteRoute'    => match (request()->routeIs('retina.*')) {
                true => [
                    'name'       => 'retina.models.stored-item.delete',
                    'parameters' => [
                        'storedItem' => $storedItem->id
                    ]
                ],
                default => [
                    'name'       => 'grp.models.stored-items.delete',
                    'parameters' => [
                        'storedItem' => $storedItem->id
                    ]
                ]
            }
        ];
    }
}
