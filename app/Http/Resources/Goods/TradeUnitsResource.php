<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Mar 2024 20:31:47 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Goods;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $slug
 * @property float $net_weight
 * @property string $type
 * @property string $name
 */
class TradeUnitsResource extends JsonResource
{
    public function toArray($request): array
    {


        return [
            'slug'               => $this->slug,
            'code'               => $this->code,
            'name'               => $this->name,
            'weight'             => $this->net_weight !== null ? ($this->net_weight / 1000) . ' kg' : null,
            'type'               => $this->type,
        ];
    }
}
