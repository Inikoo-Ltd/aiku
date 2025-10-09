<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Feb 2023 22:40:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

class PickingSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'slug'              => $this->slug,
            'reference'         => $this->reference,
            'state'         => $this->state,
            'start_at'         => $this->start_at,
            'end_at'         => $this->end_at,
            'quantity_picked'       => $this->quantity_picked,
            'quantity_packed'       => $this->quantity_packed,
            'picking_percentage'       => $this->picking_percentage. '%',
            'packing_percentage'       => $this->packing_percentage. '%'
        ];
    }
}
