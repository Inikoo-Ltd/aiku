<?php

/*
 * author Arya Permana - Kirin
 * created on 18-10-2024-15h-15m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;

class ShippingZoneResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'code' => $this->code,
            'name' => $this->name,
            'price' => $this->price,
            'territories' => $this->territories,
            'position' => $this->position,
            'created_at' => $this->created_at,
        ];
    }
}
