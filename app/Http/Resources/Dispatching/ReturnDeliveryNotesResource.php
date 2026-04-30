<?php

/*
 * author Louis Perez
 * created on 30-04-2026-15h-19m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

class ReturnDeliveryNotesResource extends JsonResource
{
    public function toArray($request): array
    {
        
        return [
            'id'                    => $this->id,
            'reference'             => $this->reference,
            'date'                  => $this->date,
            'state'                 => $this->state,
            'state_icon'            => $this->state->stateIcon()[$this->state->value],
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
            'slug'                  => $this->slug,
            'customer_slug'         => $this->customer_slug,
            'customer_name'         => $this->customer_name,
            'shop_name'             => $this->shop_name,
            'shop_slug'             => $this->shop_slug,
            'organisation_name'     => $this->organisation_name,
            'organisation_slug'     => $this->organisation_slug,
            'customer_notes'        => $this->customer_notes,
            'internal_notes'        => $this->internal_notes,
            'public_notes'          => $this->public_notes,
            'shipping_notes'        => $this->shipping_notes,
        ];
    }
}
