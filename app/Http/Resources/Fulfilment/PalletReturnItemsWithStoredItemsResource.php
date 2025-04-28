<?php

/*
 * author Arya Permana - Kirin
 * created on 07-02-2025-13h-43m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Fulfilment;

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletStoredItem\PalletStoredItemStateEnum;
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
class PalletReturnItemsWithStoredItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        $storedItem = StoredItem::find($this->id);
        // dd($this->pallet_return_state === PalletStateEnum::PICKING->value);
        return [
            'id'                     => $this->id,
            'slug'                   => $this->slug,
            'reference'              => $this->reference,
            'name'                   => $this->name,
            'total_quantity_ordered' => (int) ($this->total_quantity_ordered ?? 0),
            'is_checked'             => (bool) $this->pallet_return_state === PalletStateEnum::IN_PROCESS->value ? $this->pallet_return_id : false,
            'pallet_return_state'    => $this->pallet_return_state ?? null,
            'pallet_stored_items'    => $storedItem->palletStoredItems()
                ->when(
                    in_array($this->pallet_return_state, [PalletStateEnum::PICKED->value, PalletStateEnum::DISPATCHED->value]),
                    fn ($query) => $query->where(function ($q) {
                        $q->where('state', '!=', PalletStoredItemStateEnum::RETURNED)
                        ->orWhereHas(
                            'palletReturnItems',
                            fn ($subQuery) =>
                            $subQuery->where('pallet_return_id', $this->pallet_return_id)
                        );
                    }),
                    fn ($query) => $query->where('state', '!=', PalletStoredItemStateEnum::RETURNED)
                )
                ->get()
                ->map(function ($palletStoredItem) {
                    $palletReturnItem = $palletStoredItem->palletReturnItems
                        ->where('pallet_return_id', $this->pallet_return_id)
                        ->first();
                    return [
                        'ordered_quantity'              => (int) $palletStoredItem->quantity_ordered,
                        'id'                         => $palletStoredItem->id,
                        'reference'                  => $palletStoredItem->pallet->reference ?? null,
                        'selected_quantity'          => (int) ($palletReturnItem->quantity_ordered ?? 0),
                        'available_quantity'         => (int) $palletStoredItem->quantity,
                        'max_quantity'               => (int) $palletStoredItem->quantity,
                        'quantity_in_pallet'         => (int) $palletStoredItem->quantity,
                        'available_to_pick_quantity' => (int) ($palletReturnItem->quantity_ordered ?? 0),
                        'picked_quantity'            => (int) ($palletReturnItem->quantity_picked ?? 0),
                        'pallet_id'                  => $palletStoredItem->pallet_id,
                        'state'                      => $palletReturnItem->state ?? null,
                        'pallet_return_item_id'      => $palletReturnItem->id ?? null,
                        'all_items_returned' => $palletStoredItem->pallet?->palletStoredItems?->every(fn ($item) => $item->state == PalletStoredItemStateEnum::RETURNED),
                        'is_pallet_returned' => $palletStoredItem->pallet?->status == PalletStatusEnum::RETURNED,

                        'syncRoute' => match (request()->routeIs('retina.*')) {
                            true => [
                                'name'       => 'retina.models.pallet-return.stored_item.attach',
                                'parameters' => [
                                    'palletReturn'     => $this->pallet_return_id,
                                    'palletStoredItem' => $palletStoredItem->id
                                ],
                                'method'    => 'post'
                            ],
                            default => [
                                'name'       => 'grp.models.pallet-return.stored_item.store',
                                'parameters' => [
                                    'palletReturn'     => $this->pallet_return_id,
                                    'palletStoredItem' => $palletStoredItem->id
                                ],
                                'method'    => 'post'
                            ]
                        },
                        'newPickRoute' => [
                            'name'       => 'grp.models.pallet-return.pallet_return_item.new_pick',
                            'parameters' => [
                                'palletReturn'     => $this->pallet_return_id,
                                'palletStoredItem' => $palletStoredItem->id
                            ],
                            'method'    => 'post'
                        ],
                        'updateRoute' => [
                            'name'       => 'grp.models.pallet-return-item.pick',
                            'parameters' => [
                                'palletReturnItem' => $palletReturnItem->id ?? null
                            ],
                            'method'    => 'patch'
                        ],
                        'undoRoute' => [
                            'name'      => 'grp.models.pallet-return-item.undo-picking-stored-item',
                            'parameters' => [
                                'palletReturnItem' => $palletReturnItem->id ?? null
                            ],
                            'method'    => 'patch'
                        ],
                        'location' => isset($palletStoredItem->pallet, $palletStoredItem->pallet->location) ? [
                            'slug' => $palletStoredItem->pallet->location->slug ?? null,
                            'code' => $palletStoredItem->pallet->location->code ?? null
                        ] : null
                    ];
                }),
            'total_quantity' => (int) $this->total_quantity,
        ];
    }
}
