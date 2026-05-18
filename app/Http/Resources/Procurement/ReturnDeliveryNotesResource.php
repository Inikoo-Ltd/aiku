<?php

/*
 * author Louis Perez
 * created on 30-04-2026-15h-19m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\GoodsIn\ReturnDeliveryNote;

class ReturnDeliveryNotesResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var ReturnDeliveryNote $returnDeliveryNote */
        $returnDeliveryNote = $this;

        return [
            'id'                    => $returnDeliveryNote->id,
            'reference'             => $returnDeliveryNote->reference,
            'date'                  => $returnDeliveryNote->date,
            'state'                 => $returnDeliveryNote->state,
            'state_icon'            => $returnDeliveryNote->state->stateIcon()[$returnDeliveryNote->state->value],
            'created_at'            => $returnDeliveryNote->created_at,
            'updated_at'            => $returnDeliveryNote->updated_at,
            'slug'                  => $returnDeliveryNote->slug,
            'customer_slug'         => $returnDeliveryNote->customer_slug,
            'customer_name'         => $returnDeliveryNote->customer_name,
            'shop_name'             => $returnDeliveryNote->shop_name,
            'shop_slug'             => $returnDeliveryNote->shop_slug,
            'organisation_name'     => $returnDeliveryNote->organisation_name,
            'organisation_slug'     => $returnDeliveryNote->organisation_slug,
            'customer_notes'        => $returnDeliveryNote->customer_notes,
            'internal_notes'        => $returnDeliveryNote->internal_notes,
            'public_notes'          => $returnDeliveryNote->public_notes,
            'shipping_notes'        => $returnDeliveryNote->shipping_notes,
        ];
    }
}
