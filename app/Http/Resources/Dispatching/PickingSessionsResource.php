<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Feb 2023 22:40:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;


class PickingSessionsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'slug'                  => $this->slug,
            'reference'             => $this->reference,
            'state'                 => $this->state,
            'state_icon'            =>  $this->state->stateIcon()[$this->state->value],
            'start_at'              => $this->start_at,
            'end_at'                => $this->end_at,
            'number_delivery_notes' => $this->number_delivery_notes,
            'number_items'          => $this->number_items,
            'user_id'               => $this->user_id,
            'user_username'         => $this->user_username,
            'user_name'             => $this->user_name,
            'quantity_picked'       => $this->quantity_picked,
            'quantity_packed'       => $this->quantity_packed,
            'picking_percentage'       => $this->picking_percentage. '%',
            'packing_percentage'       => $this->packing_percentage. '%'


        ];
    }
}
