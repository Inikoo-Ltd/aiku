<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Feb 2023 22:40:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'state'               => $this->state,
            'state_icon'          => $this->state->stateIcon()[$this->state->value],
            'quantity_required'   => intVal($this->quantity_required),
            'quantity_picked'     => intVal($this->quantity_picked),
            'quantity_packed'     => intVal($this->quantity_packed),
            'quantity_dispatched' => intVal($this->quantity_dispatched),
            'org_stock_code'      => $this->org_stock_code,
            'org_stock_name'      => $this->org_stock_name
        ];
    }
}
