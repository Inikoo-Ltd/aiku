<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

class OrgStockMovementsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date,
            'class' => $this->class,
            'type' => $this->type,
            'flow' => $this->flow,
            'quantity' => $this->quantity,
            'org_stock_name' => $this->org_stock_name,
            'org_stock_slug' => $this->org_stock_slug,
            'org_amount' => $this->org_amount,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'warehouse_name' => $this->warehouse_name,
            'warehouse_slug' => $this->warehouse_slug,
            'location_code' => $this->location_code,
            'location_slug' => $this->location_slug,
            'operation_type' => $this->operation_type,
            'operation_id' => $this->operation_id,
        ];
    }
}
