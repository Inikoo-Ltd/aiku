<?php

namespace App\Http\Resources\Dispatching;

use App\Http\Resources\HasSelfCall;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteReturnOptionResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request)
    {
        /** @var DeliveryNote $deliveryNote */
        $deliveryNote = $this->resource;

        return [
            'id'                => $deliveryNote->id,
            'label'             => "{$deliveryNote->reference} | {$deliveryNote->customer_name} ({$deliveryNote->date})",
            'reference'         => $deliveryNote->reference,
            'slug'              => $deliveryNote->slug,
            'customer_name'     => $deliveryNote->customer_name,
            'date'              => $deliveryNote->date,
        ];
    }
}
