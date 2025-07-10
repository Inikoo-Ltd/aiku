<?php
/*
 * author Arya Permana - Kirin
 * created on 10-07-2025-16h-32m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/
namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

class BoxesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'slug'                => $this->slug,
            'name'                => $this->name,
            'stock'               => $this->stock,
            'dimension'           => $this->dimension,
            'height'              => $this->height,
            'width'               => $this->width,
            'depth'               => $this->depth,
        ];
    }
}
