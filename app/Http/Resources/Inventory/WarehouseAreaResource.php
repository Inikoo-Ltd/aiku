<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 15 Sept 2022 20:32:13 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property int|null $picking_position
 * @property int $number_locations
 * @property float $stock_value
 * @property int $number_empty_locations
 * @property string $warehouse_slug
 * @property string $organisation_slug
 * @property string $organisation_name
 */
class WarehouseAreaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'slug'                   => $this->slug,
            'code'                   => $this->code,
            'name'                   => $this->name,
            'picking_position'       => $this->picking_position,
            'number_locations'       => $this->number_locations,
            'stock_value'            => $this->stock_value,
            'number_empty_locations' => $this->number_empty_locations,
            'warehouse_slug'         => $this->warehouse_slug,
            'organisation_slug'      => $this->organisation_slug,
            'organisation_name'      => $this->organisation_name,
        ];
    }
}
