<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 15 Sept 2022 20:32:13 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use App\Models\Inventory\LocationStats;
use Illuminate\Http\Resources\Json\JsonResource;

/**
* @property string $code
* @property mixed $pivot
* @property int $id
* @property string $slug
* @property string $warehouse_slug
*/
class WarehouseAreaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'slug'             => $this->slug,
            'code'             => $this->code,
            'number_locations' => $this->whenPivotLoaded(new LocationStats(), function () {
                return $this->pivot->number_locations;
            }),
            'warehouse_slug'     => $this->warehouse_slug,
        ];
    }
}
