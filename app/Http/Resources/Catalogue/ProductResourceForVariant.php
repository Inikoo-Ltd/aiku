<?php

/*
 * author Louis Perez
 * created on 30-12-2025-16h-01m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Traits\HasPriceMetrics;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResourceForVariant extends JsonResource
{
    use HasSelfCall;
    use HasPriceMetrics;

    public function toArray($request): array
    {
        return [
            'id'                            => $this->id,
            'name'                          => $this->name,
            'code'                          => $this->code,
            'image'                         => $this->web_images,
            'slug'                          => $this->slug,
        ];
    }
}
