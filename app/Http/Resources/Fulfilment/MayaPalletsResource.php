<?php

/*
 * author Arya Permana - Kirin
 * created on 25-03-2025-14h-33m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Fulfilment;

use Illuminate\Http\Resources\Json\JsonResource;

class MayaPalletsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'customer_reference' => $this->customer_reference,
            'slug' => $this->slug ?? null,
            'state' => $this->state,
            'status' => $this->status,
            'notes' => $this->notes ?? '',
            'rental_id' => $this->rental_id,
            'status_label' => $this->status->labels()[$this->status->value],
            'state_icon' => $this->state->stateIcon()[$this->state->value],
            'status_icon' => $this->status->statusIcon()[$this->status->value],
            'fulfilment_customer_name' => $this->fulfilment_customer_name,
        ];
    }
}
