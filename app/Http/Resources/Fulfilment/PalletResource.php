<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Http\Resources\Inventory\LocationResource;
use App\Models\Fulfilment\Pallet;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $customer_reference
 * @property string $state
 * @property string $status
 * @property string $notes
 * @property \App\Models\Fulfilment\FulfilmentCustomer $fulfilmentCustomer
 * @property \App\Models\Inventory\Location $location
 * @property \App\Models\Inventory\Warehouse $warehouse
 * @property \App\Models\Fulfilment\StoredItem $storedItems
 */
class PalletResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Pallet $pallet */
        $pallet = $this;

        $timeline = [];
        foreach (PalletStateEnum::cases() as $state) {
            $timeline[] = [
                'label'     => $state->labels()[$state->value],
                'tooltip'   => $state->labels()[$state->value],
                'key'       => $state->value,
                'icon'      => $state->stateIcon()[$state->value]['icon'],
                'current'   => $state == $this->state,
                'timestamp' => $this->{$state->snake() . '_at'} ? $this->{$state->snake() . '_at'}->toISOString() : null
            ];
        }

        return [
            'id'                    => $this->id,
            'reference'             => $pallet->reference,
            'customer_reference'    => $pallet->customer_reference,
            'slug'                  => $pallet->slug ?? null,
            'customer'              => [
                'name'                 => $this->fulfilmentCustomer->customer->name,
                'contact_name'         => $this->fulfilmentCustomer->customer->contact_name,
                'route'                => [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show',
                    'parameters' => [$pallet->organisation->slug, $pallet->fulfilment->slug, $pallet->fulfilmentCustomer->slug]
                                    ]
                ],

            'location'              => $pallet->location ? [
                                    'resource' => LocationResource::make($this->location),
                                    'route'    => $request->user()->authTo("locations.{$pallet->warehouse_id}.view") ? [
                                        'name'       => 'grp.org.warehouses.show.fulfilment.locations.show',
                                        'parameters' => [$pallet->organisation->slug, $pallet->warehouse->slug, $pallet->location->slug]
                                    ] : null
                ] : null,
            'state'                 => $this->state,
            'status'                => $this->status,
            'notes'                 => $this->notes ?? '',
            'rental_id'             => $this->rental_id,
            'status_label'          => $pallet->status->labels()[$pallet->status->value],
            'state_icon'           => $pallet->state->stateIcon()[$pallet->state->value],
            'status_icon'           => $pallet->status->statusIcon()[$pallet->status->value],
            'items'                 => StoredItemResource::collection($this->storedItems ?? []),
            'timeline'              => $timeline,
            'audit'                 => $pallet->storedItemAudits()->where('state', StoredItemAuditStateEnum::IN_PROCESS)->first()?->id,
            'pallet_delivery'       => $pallet->palletDelivery ? [
                'reference' => $pallet->palletDelivery->reference,
                'route'     => [
                    'name' => 'grp.org.fulfilments.show.operations.pallet-deliveries.show',
                    'parameters' => [
                        'organisation' => $pallet->organisation->slug,
                        'fulfilment' => $pallet->fulfilment->slug,
                        'palletDelivery' => $pallet->palletDelivery->slug
                    ]
                ]
            ] : [],
            'pallet_return'         => $pallet->palletReturn ? [
                'reference' => $pallet->palletReturn->reference,
                'route'     => [
                    'name' => $pallet->palletReturn->type == PalletReturnTypeEnum::PALLET ? 'grp.org.fulfilments.show.operations.pallet-returns.show' : 'grp.org.fulfilments.show.operations.pallet-return-with-stored-items.show',
                    'parameters' => [
                        'organisation' => $pallet->organisation->slug,
                        'fulfilment' => $pallet->fulfilment->slug,
                        'palletReturn' => $pallet->palletReturn->slug
                    ]
                ]
            ] : []
        ];
    }
}
