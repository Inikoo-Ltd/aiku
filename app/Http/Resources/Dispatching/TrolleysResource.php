<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 14:41:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $name
 * @property mixed $slug
 */
class TrolleysResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'current_delivery_note' => $this->deliveryNote ? [
                'id'            => $this->deliveryNote->id,
                'state_icon'         => DeliveryNoteStateEnum::from($this->deliveryNote->state->value)->stateIcon()[$this->deliveryNote->state->value],
                'slug'          => $this->deliveryNote->slug,
                'reference'     => $this->deliveryNote->reference,
                'number_items'  => $this->deliveryNote->number_items,
            ] : null,
        ];
    }
}
