<?php

/*
 * Author: Nickel
 * Created: Tue, 01 Apr 2026
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

class LocationOrgStockHistoriesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                           => $this->id,
            'date'                         => $this->date,
            'stock_id'                     => $this->stock_id,
            'stock_code'                   => $this->stock_code,
            'stock_name'                   => $this->stock_name,
            'stock_slug'                   => $this->stock_slug,
            'location_code'                => $this->location_code,
            'actual_quantity_in_locations' => $this->actual_quantity_in_locations,
            'quantity_in_locations'        => $this->quantity_in_locations,
        ];
    }
}
