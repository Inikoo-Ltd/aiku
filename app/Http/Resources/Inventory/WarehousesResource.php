<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 15 Sept 2022 14:55:27 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use App\Enums\Inventory\Warehouse\WarehouseStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property int $number_locations
 * @property int $number_warehouse_areas
 * @property WarehouseStateEnum $state
 */
class WarehousesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug' => $this->slug,
            'code' => $this->code,
            'name' => $this->name,
            'number_locations' => $this->number_locations,
            'number_warehouse_areas' => $this->number_warehouse_areas,
            'state_icon' => $this->state->stateIcon()[$this->state->value],
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
        ];
    }
}
