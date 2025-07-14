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
 * @property string $type
 * @property string $name
 * @property mixed $description
 * @property mixed $barcode
 * @property mixed $dimensions
 * @property mixed $gross_weight
 * @property mixed $volume
 * @property mixed $image_id
 * @property mixed $marketing_weight
 * @property mixed $marketing_dimensions
 * @property mixed $status
 */
class TradeUnitResource extends JsonResource
{
    public function toArray($request): array
    {
        return array(
            'slug'                 => $this->slug,
            'status'               => $this->status,
            'code'                 => $this->code,
            'name'                 => $this->name,
            'description'          => $this->description,
            'barcode'              => $this->barcode,
            'gross_weight'         => $this->gross_weight,
            'marketing_weight'     => $this->marketing_weight,
            'marketing_dimensions' => $this->marketing_dimensions,
            'dimensions'           => $this->dimensions,
            'volume'               => $this->volume,
            'type'                 => $this->type,
            'image_id'             => $this->image_id
        );
    }
}
