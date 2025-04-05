<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class PalletReturnResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var \App\Models\Fulfilment\PalletReturn $palletReturn */
        $palletReturn = $this;

        $timeline = [];
        foreach (PalletReturnStateEnum::cases() as $state) {
            $timeline[$state->value] = [
                'label'     => $state->labels()[$state->value],
                'tooltip'   => $state->labels()[$state->value],
                'key'       => $state->value,
                'timestamp' => $palletReturn->{$state->snake().'_at'} ? $palletReturn->{$state->snake().'_at'}->toISOString() : null
            ];
        }

        $finalTimeline = Arr::except(
            $timeline,
            [
                $palletReturn->state->value == PalletReturnStateEnum::CANCEL->value
                    ? ''
                    : PalletReturnStateEnum::CANCEL->value
            ]
        );

        $orderedItems = (int) $palletReturn->items()->sum('quantity_ordered');
        $pickedItems = (int) $palletReturn->items()->sum('quantity_picked');
        $unpickedItems = $orderedItems - $pickedItems;

        return [
            'id'                    => $palletReturn->id,
            'customer_reference'    => $palletReturn->customer_reference,
            'reference'             => $palletReturn->reference,
            'state'                 => $palletReturn->state,
            'is_collection'         => $palletReturn->is_collection,
            'state_label'           => $palletReturn->state->labels()[$palletReturn->state->value],
            'state_icon'            => $palletReturn->state->stateIcon()[$palletReturn->state->value],
            'type'                  => $palletReturn->type,
            'type_label'            => $palletReturn->type->labels()[$palletReturn->type->value],
            'type_icon'             => $palletReturn->type->stateIcon()[$palletReturn->type->value],
            'timeline'              => $finalTimeline,
            'estimated_delivery_date' => $palletReturn->estimated_delivery_date,

            'number_ordered_items'  => $orderedItems,
            'number_picked_items'   => $pickedItems,
            'number_unpicked_items' => $unpickedItems,
            'number_stored_items'   => $palletReturn->stats->number_stored_items,

            'number_pallets'        => $palletReturn->stats->number_pallets,
            'number_services'       => $palletReturn->stats->number_services,
            'number_physical_goods' => $palletReturn->stats->number_physical_goods,
            'number_pallet_picked'    => $palletReturn->stats->number_pallets_state_picked,
            'number_boxes'          => $palletReturn->stats->number_pallets_type_box,
            'number_oversizes'      => $palletReturn->stats->number_pallets_type_oversize,
            'number_pallets_state_other_incident'   => $palletReturn->stats->number_pallets_state_other_incident,
            'number_pallets_state_lost'   => $palletReturn->stats->number_pallets_state_lost,
            "number_pallets_state_damaged" => $palletReturn->stats->number_pallets_state_damaged
        ];
    }
}
