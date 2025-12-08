<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class PalletDeliveryResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var PalletDelivery $palletDelivery */
        $palletDelivery = $this;
        $timeline = [];
        foreach (PalletDeliveryStateEnum::cases() as $state) {
            $timeline[$state->value] = [
                'label' => $state->labels()[$state->value],
                'tooltip' => $state->labels()[$state->value],
                'key' => $state->value,
                'timestamp' => $palletDelivery->{$state->snake().'_at'} ? $palletDelivery->{$state->snake().'_at'}->toISOString() : null,
            ];
        }

        $finalTimeline = Arr::except(
            $timeline,
            [
                $palletDelivery->state->value == PalletDeliveryStateEnum::NOT_RECEIVED->value
                    ? PalletDeliveryStateEnum::BOOKED_IN->value
                    : PalletDeliveryStateEnum::NOT_RECEIVED->value,
            ]
        );

        return [
            'id' => $palletDelivery->id,
            'customer_name' => $palletDelivery->fulfilmentCustomer->customer->name,
            'customer_reference' => $palletDelivery->customer_reference,
            'reference' => $palletDelivery->reference,
            'state' => $palletDelivery->state->value,
            'timeline' => $finalTimeline,
            'number_pallets' => $palletDelivery->stats->number_pallets_type_pallet,
            'number_boxes' => $palletDelivery->stats->number_pallets_type_box,
            'number_oversizes' => $palletDelivery->stats->number_pallets_type_oversize,
            'number_services' => $palletDelivery->stats->number_services,
            'number_physical_goods' => $palletDelivery->stats->number_physical_goods,
            'state_label' => $palletDelivery->state->labels()[$palletDelivery->state->value],
            'state_icon' => $palletDelivery->state->stateIcon()[$palletDelivery->state->value],
            'estimated_delivery_date' => $palletDelivery->estimated_delivery_date,
            'public_notes' => $palletDelivery->public_notes,
            'number_pallet_storing' => $palletDelivery->stats->number_pallets_state_storing,
            'number_pallets_state_other_incident' => $palletDelivery->stats->number_pallets_state_other_incident,
            'number_pallets_state_lost' => $palletDelivery->stats->number_pallets_state_lost,
            'number_pallets_state_damaged' => $palletDelivery->stats->number_pallets_state_damaged,
            'number_pallets_state_booked_in' => $palletDelivery->stats->number_pallets_state_booked_in,
            'number_pallets_state_not_received' => $palletDelivery->stats->number_pallets_state_not_received,
        ];
    }
}
