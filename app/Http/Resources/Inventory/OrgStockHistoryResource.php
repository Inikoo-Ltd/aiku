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
            'id'                    => $this->id,
            'slug'                  => $this->slug,
            'code'                  => $this->code,
            'name'                  => $this->name,
            'date'                  => $this->date,
            'quantity_in_locations' => $this->quantity_in_locations,
            'org_stock_value'       => $this->org_stock_value,
            'grp_stock_value'       => $this->grp_stock_value,
            'currency_code'         => $this->currency_code,
            'sold_within_1y'        => [
                'icon'    => 'fal fa-cash-register',
                'class'   => $this->sold_within_1y ? 'text-green-500' : 'text-red-500',
                'tooltip' => __('Last sold at: ').($this->last_sold_date ? $this->last_sold_date->format('D, M j, Y') : __('Never')),
            ],
            'non_moving_1y'         => $this->non_moving_1y,
        ];
    }
}
