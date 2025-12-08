<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 25 Oct 2022 08:17:00 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property number $state
 * @property string $name
 * @property string $description
 * @property string $number_current_org_stocks
 * @property mixed $created_at
 * @property mixed $updated_at
 */
class OrgStockFamiliesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug' => $this->slug,
            'code' => $this->code,
            'state' => $this->state,
            'name' => $this->name,
            'number_current_org_stocks' => $this->number_current_org_stocks,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'warehouse_slug' => $this->warehouse_slug,
        ];
    }
}
