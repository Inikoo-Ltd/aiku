<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-11h-54m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Fulfilment;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $date
 * @property mixed $name
 * @property mixed $reference
 * @property mixed $slug
 * @property mixed $state
 * @property mixed $number_item_transactions
 */
class RetinaDropshippingOrdersResources extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                       => $this->id,
            'date'                     => $this->date,
            'name'                     => $this->name,
            'reference'                => $this->reference,
            'slug'                     => $this->slug,
            'client_name'              => $this->customerClient?->contact_name,
            'state'                    => $this->state,
            'number_item_transactions' => $this->number_item_transactions,
            'state_label'              => $this->state->labels()[$this->state->value],
            'state_icon'               => $this->state->stateIcon()[$this->state->value]
        ];
    }
}
