<?php

/*
 * author Arya Permana - Kirin
 * created on 30-01-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Fulfilment;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $customer_name
 * @property string $customer_slug
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $reference
 * @property mixed $customer_reference
 * @property mixed $number_pallets
 * @property mixed $estimated_delivery_date
 * @property mixed $state
 */
class SpacesResource extends JsonResource
{
    public function toArray($request): array
    {

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'reference' => $this->reference,
            'state' => $this->state,
            'state_label' => $this->state->labels()[$this->state->value],
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'rental_slug' => $this->rental_slug,
            'rental_name' => $this->rental_name,
            'rental_code' => $this->rental_code,
            'rental' => $this->rental_code." ($this->currency_symbol$this->rental_price/$this->rental_unit)",
        ];
    }
}
