<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 22:08:01 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Ordering;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $shipping_zone_schema_id
 * @property int $shop_id
 * @property string $slug
 * @property string $code
 * @property string $territories
 * @property string $price
 * @property bool $status
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 */
class ShippingZoneResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'shop_id' => $this->shop_id,
            'shipping_zone_schema_id' => $this->shipping_zone_schema_id,
            'slug' => $this->slug,
            'code' => $this->code,
            'name' => $this->name,
            'territories' => $this->territories,
            'price' => $this->price,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
