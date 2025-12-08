<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 22:08:01 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Ordering;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $shop_id
 * @property string $name
 * @property string $slug
 * @property bool $status
 * @property mixed $created_at
 * @property mixed $updated_at
 */
class ShippingZoneSchemaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'shop_id' => $this->shop_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
