<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Mar 2024 13:28:31 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $pivot
 * @property mixed $id
 * @property mixed $slug
 */
class LocationsResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        return [
            'id'                       => $this->id,
            'slug'                     => $this->slug,
            'code'                     => $this->code,
            'stock_value'              => $this->stock_value ?? 0,
            'stock_commercial_value'   => $this->stock_commercial_value,
            'allow_stocks'             => $this->allow_stocks,
            'allow_fulfilment'         => $this->allow_fulfilment,
            'allow_dropshipping'       => $this->allow_dropshipping,
            'has_stock_slots'          => $this->has_stock_slots,
            'has_fulfilment'           => $this->has_fulfilment,
            'has_dropshipping_slots'   => $this->has_dropshipping_slots,
            'organisation_slug'        => $this->organisation_slug,
            'organisation_name'        => $this->organisation_name,
            'warehouse_slug'           => $this->warehouse_slug,
            'max_weight'               => $this->max_weight ?? 0,
            'max_volume'               => $this->max_volume ?? 0,
            'number_org_stock_slots'   => $this->number_org_stock_slots,
            'number_empty_stock_slots' => $this->number_empty_stock_slots,

            'quantity' => $this->whenPivotLoaded(new LocationOrgStock(), function () {
                return $this->pivot->quantity;
            }),
        ];
    }
}
