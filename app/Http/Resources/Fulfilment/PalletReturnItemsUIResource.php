<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Feb 2024 11:07:20 Malaysia Time, Bali Airport, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
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
class PalletReturnItemsUIResource extends JsonResource
{
    public function toArray($request): array
    {
        $pallet = Pallet::find($this->pallet_id);

        return [
            'id' => $this->id,
            'pallet_id' => $this->pallet_id,
            'slug' => $this->slug,
            'reference' => $this->reference,
            'customer_reference' => (string) $this->customer_reference,
            'fulfilment_customer_name' => $this->fulfilment_customer_name,
            'fulfilment_customer_slug' => $this->fulfilment_customer_slug,
            'fulfilment_customer_id' => $this->fulfilment_customer_id,
            'notes' => (string) $this->notes,
            'state' => $this->state->value,
            'type_icon' => $this->type->typeIcon()[$this->type->value],
            'type' => $this->type,
            'state_label' => PalletStateEnum::labels()[$this->state->value],
            'state_icon' => PalletStateEnum::stateIcon()[$this->state->value],
            'status' => $this->status,
            'status_label' => $this->status->labels()[$this->status->value],
            'status_icon' => $this->status->statusIcon()[$this->status->value],
            'location' => $this->location_slug,
            'location_code' => $this->location_code,
            'location_id' => $this->location_id,
            'is_checked' => $this->pallet_return_state === PalletReturnStateEnum::IN_PROCESS->value ? (bool) $this->pallet_return_id : false,
            'stored_items' => $pallet->storedItems->map(fn ($storedItem) => [
                'reference' => $storedItem->reference,
                'quantity' => (int) $storedItem->pivot->quantity,
            ]),
            'stored_items_quantity' => (int) $pallet->storedItems()->sum('quantity'),
            'syncRoute' => match (request()->routeIs('retina.*')) {
                true => [
                    'name' => 'retina.models.pallet.pallet-return-item.update',
                    'parameters' => $this->id,
                ],
                default => [
                    'name' => 'grp.models.pallet.pallet-return-item.sync',
                    'parameters' => $this->id,
                ]
            },
            'attachRoute' => match (request()->routeIs('retina.*')) {
                true => [
                    'name' => 'retina.models.pallet-return.pallet.attach',
                    'parameters' => [
                        'palletReturn' => null, // add in FE , BE didnt get access to it
                        'pallet' => $this->pallet_id,

                    ],
                ],
                default => [
                    'name' => 'grp.models.pallet-return.pallet.attach',
                    'parameters' => [
                        'palletReturn' => null, // add in FE , BE didnt get access to it
                        'pallet' => $this->pallet_id,

                    ],
                ]
            },
            'updateRoute' => match (request()->routeIs('retina.*')) {
                true => [
                    'name' => 'retina.models.pallet-return-item.update',
                    'parameters' => $this->id,
                ],
                default => [
                    'name' => 'grp.models.pallet-return-item.set_as_picked',
                    'parameters' => $this->id,
                ]
            },
            'undoPickingRoute' => [
                'name' => 'grp.models.pallet-return-item.undo-picking',
                'parameters' => [$this->id],
            ],
            'undoConfirmedRoute' => [
                'name' => 'grp.models.pallet-return-item.undo-confirmed',
                'parameters' => [$this->id],
            ],
            'notPickedRoute' => [
                'method' => 'patch',
                'name' => 'grp.models.pallet-return-item.not-picked',
                'parameters' => [$this->id],
            ],
            'deleteRoute' => match (request()->routeIs('retina.*')) {
                true => [
                    'name' => 'retina.models.pallet.delete',
                    'parameters' => $this->id,
                ],
                default => [
                    'name' => 'grp.models.pallet.delete',
                    'parameters' => $this->id,
                ]
            },
            'deleteFromDeliveryRoute' => match (request()->routeIs('retina.*')) {
                true => [
                    'name' => 'retina.models.pallet-delivery.pallet.delete',
                    'parameters' => [$this->pallet_delivery_id, $this->id],
                ],
                default => [
                    'name' => 'grp.models.fulfilment-customer.pallet-delivery.pallet.delete',
                    'parameters' => [$this->fulfilment_customer_id, $this->pallet_delivery_id, $this->id],
                ]
            },
            'deleteFromReturnRoute' => match (request()->routeIs('retina.*')) {
                true => [
                    'name' => 'retina.models.pallet-return.pallet.delete',
                    'parameters' => [$this->pallet_return_id, $this->pallet_id],
                ],
                default => [
                    'name' => 'grp.models.pallet-return.pallet.detach',
                    'parameters' => [$this->pallet_return_id, $this->pallet_id],
                ]
            },
            'notReceivedRoute' => [
                'name' => 'grp.models.pallet.not-received',
                'parameters' => [$this->id],
            ],
            'undoNotReceivedRoute' => [
                'name' => 'grp.models.pallet.undo-not-received',
                'parameters' => [$this->id],
            ],
            'bookInRoute' => [
                'name' => 'grp.models.pallet.book_in',
                'parameters' => [$this->id],
            ],
            'undoBookInRoute' => [
                'name' => 'grp.models.pallet.undo_book_in',
                'parameters' => [$this->id],
            ],
            'updateLocationRoute' => [
                'name' => 'grp.models.warehouse.pallets.location.update',
                'parameters' => [$this->warehouse_id, $this->id],
            ],
            'storeStoredItemRoute' => match (request()->routeIs('retina.*')) {
                true => [
                    'name' => 'retina.models.pallet.stored-items.update',
                    'parameters' => [$this->id],
                ],
                default => [
                    'name' => 'grp.models.pallet.stored-items.update',
                    'parameters' => [$this->id],
                ]
            },
            'updatePalletRoute' => match (request()->routeIs('retina.*')) {
                true => [
                    'name' => 'retina.models.pallet.update',
                    'parameters' => $this->pallet_id,
                ],
                default => [
                    'name' => 'grp.models.pallet.update',
                    'parameters' => $this->pallet_id,
                ]
            },
        ];
    }
}
