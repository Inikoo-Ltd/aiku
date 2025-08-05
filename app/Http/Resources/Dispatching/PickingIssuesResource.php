<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Feb 2023 22:40:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

class PickingIssuesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                         => $this->id,
            'slug'                       => $this->slug,
            'reference'                  => $this->reference,
            'is_solved'                  => $this->is_solved,
            'issuer'                     => $this->issuer?->contact_name ?? '',
            'resolver'                   => $this->resolver?->contact_name ?? '',
            'delivery_note_issue'        => $this->delivery_note_issue,
            'delivery_note_item_issue'   => $this->delivery_note_item_issue,
        ];
    }
}
