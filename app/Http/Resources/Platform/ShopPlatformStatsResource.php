<?php

/*
 * author Arya Permana - Kirin
 * created on 02-04-2025-15h-09m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Platform;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $code
 * @property mixed $name
 * @property mixed $type
 */
class ShopPlatformStatsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'      => $this->id,
            'code'    => $this->platform->code,
            'slug'    => $this->platform->slug,
            'name'    => $this->platform->name,
            'type'    => $this->platform->type,
            'number_customers' => $this->number_customers,
            'number_customer_sales_channels' => $this->number_customer_sales_channels,
            'number_customer_sales_channel_broken' => $this->number_customer_sales_channel_broken,
            'number_products' => $this->number_products,
            'number_orders' => $this->number_orders
        ];
    }
}
