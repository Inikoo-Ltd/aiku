<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\GoodsIn;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string|null $notes
 * @property int|null $image_id
 * @property \Illuminate\Support\Carbon $created_at
 */
class UnidentifiedReturnsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'warehouse_slug' => $this->warehouse->slug,
            'notes'      => $this->notes,
            'image'      => $this->image_id ? $this->imageSources(400, 400) : null,
            'created_at' => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}
