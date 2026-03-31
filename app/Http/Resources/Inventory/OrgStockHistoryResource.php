<?php

/*
 * Author: Nickel
 * Created: Tue, 01 Apr 2026
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

class OrgStockHistoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'code'=>$this->code,
            'name'=>$this->name,
            'id'                    => $this->id,
            'date'                  => $this->date,
            'quantity_in_locations' => $this->quantity_in_locations,
            'org_stock_value'       => $this->org_stock_value,
            'grp_stock_value'       => $this->grp_stock_value,
            'number_locations'      => $this->number_locations,
            'value_per_sku'         => $this->value_per_sku,
        ];
    }
}
