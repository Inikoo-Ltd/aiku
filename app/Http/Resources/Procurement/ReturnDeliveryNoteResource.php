<?php

/*
 * author Louis Perez
 * created on 30-04-2026-15h-58m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Procurement;

use App\Http\Resources\HasSelfCall;
use App\Models\GoodsIn\ReturnDeliveryNote;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnDeliveryNoteResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var ReturnDeliveryNote $returnDeliveryNote */
        $returnDeliveryNote = $this;

        return [
            'id'                             => $returnDeliveryNote->id,
            'slug'                           => $returnDeliveryNote->slug,
            'reference'                      => $returnDeliveryNote->reference,
            'date'                           => $returnDeliveryNote->date,
            'state'                          => $returnDeliveryNote->state,
            'updated_at'                     => $returnDeliveryNote->updated_at,
        ];
    }
}
