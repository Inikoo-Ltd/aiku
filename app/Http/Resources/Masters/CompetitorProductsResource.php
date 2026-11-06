<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Masters;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\Masters\CompetitorProduct $resource
 */
class CompetitorProductsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'code'          => $this->code,
            'name'          => $this->name,
            'url'           => $this->url,
            'image_url'     => $this->image_url,
            'price'         => $this->price === null ? null : (float) $this->price,
            'was_price'     => $this->was_price === null ? null : (float) $this->was_price,
            'discount'      => $this->was_price > $this->price && $this->was_price > 0 ? (int) round(100 * ($this->was_price - $this->price) / $this->was_price) : null,
            'rrp'           => $this->rrp === null ? null : (float) $this->rrp,
            'units'         => (float) $this->units,
            'minimum_order' => $this->minimum_order,
            'fetched_at'    => $this->fetched_at,
        ];
    }
}
