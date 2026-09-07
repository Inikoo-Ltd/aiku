<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $location_slug
 * @property mixed $location_code
 * @property mixed $quantity
 */
class NegativeLocationOrgStocksResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'slug'          => $this->slug,
            'code'          => $this->code,
            'name'          => $this->name,
            'location_slug' => $this->location_slug,
            'location_code' => $this->location_code,
            'quantity'      => number_format((float)$this->quantity, 3),
        ];
    }
}
