<?php

/*
 * author Arya Permana - Kirin
 * created on 04-12-2024-14h-37m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Goods;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property number $state
 * @property string $name
 * @property string $description
 * @property string $number_current_stocks
 * @property mixed $created_at
 * @property mixed $updated_at
 */
class IngredientsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'number_trade_units' => $this->number_trade_units,
            'number_stocks' => $this->number_stocks,
            'number_master_products' => $this->number_master_products,
        ];
    }
}
