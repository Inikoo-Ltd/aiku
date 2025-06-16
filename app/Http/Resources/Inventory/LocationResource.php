<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 16 Sept 2022 23:18:32 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $pivot
 */
class LocationResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Location $location */
        $location = $this;

        return [
            'id'                     => $location->id,
            'slug'                   => $location->slug,
            'code'                   => $location->code,
            'allow_stocks'           => $location->allow_stocks,
            'allow_fulfilment'       => $location->allow_fulfilment,
            'allow_dropshipping'     => $location->allow_dropshipping,
            'has_stock_slots'        => $location->has_stock_slots,
            'has_fulfilment'         => $location->has_fulfilment,
            'has_dropshipping_slots' => $location->has_dropshipping_slots,
            'status'                 => $location->status,
            'stock_value'            => $location->stock_value,
            'is_empty'               => $location->is_empty,
            'max_weight'             => $location->max_weight,
            'max_volume'             => $location->max_volume,
            'data'                   => $location->data,
            'audited_at'             => $location->audited_at,
            'created_at'             => $location->created_at,
            'updated_at'             => $location->updated_at,
            'quantity'               => $this->whenPivotLoaded(new LocationOrgStock(), function () {
                return $this->pivot->quantity;
            }),
        ];
    }
}
