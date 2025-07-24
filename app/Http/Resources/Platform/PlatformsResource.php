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
class PlatformsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'      => $this->id,
            'code'    => $this->code,
            'slug'    => $this->slug,
            'name'    => $this->name,
            'type'    => $this->type,
            'number_customers' => $this->stats?->number_customers,
            'number_products' => $this->stats?->number_products,
            'number_orders' => $this->stats?->number_orders
        ];
    }
}
