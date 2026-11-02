<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Nov 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $date
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $location_slug
 * @property mixed $location_code
 */
class EmptyLocationOrgStocksResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'date'          => $this->date ?? null,
            'slug'          => $this->slug,
            'code'          => $this->code,
            'name'          => $this->name,
            'location_slug' => $this->location_slug,
            'location_code' => $this->location_code,
        ];
    }
}
