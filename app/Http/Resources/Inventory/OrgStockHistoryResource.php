<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 01 Apr 2026
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $quantity_in_locations
 * @property mixed $date
 * @property mixed $org_stock_value
 * @property mixed $grp_stock_value
 * @property mixed $currency_code
 * @property mixed $sold_within_1y
 * @property mixed $last_sold_date
 * @property mixed $non_moving_1y
 */
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
                'icon'    => $this->sold_within_1y ? 'fal fa-cash-register' : 'fal fa-ban',
                'class'   => $this->sold_within_1y ? 'text-grey-500' : 'text-red-500',
                'tooltip' => __('Last sold at: ').($this->last_sold_date ? $this->last_sold_date->format('D, M j, Y') : __('Never')),
            ],
            'non_moving_1y'         => $this->non_moving_1y,
        ];
    }
}
