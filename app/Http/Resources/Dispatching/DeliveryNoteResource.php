<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Feb 2023 22:40:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use App\Http\Resources\HasSelfCall;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var DeliveryNote $deliverNote */
        $deliverNote = $this;

        return [
            'id'             => $deliverNote->id,
            'slug'           => $deliverNote->slug,
            'reference'      => $deliverNote->reference,
            'date'           => $deliverNote->date,
            'state'          => $deliverNote->state,
            'type'           => $deliverNote->type,
            'weight'         => $deliverNote->weight,
            'created_at'     => $deliverNote->created_at,
            'updated_at'     => $deliverNote->updated_at,
        ];
    }
}
