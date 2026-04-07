<?php

namespace App\Http\Resources\Helpers;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $slug
 * @property mixed $name
 * @property mixed $id
 * @property mixed $number_trade_units
 */
class BrandResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'               => $this->slug,
            'name'               => $this->name,
            'id'                 => $this->id,
            'number_trade_units' => $this->number_trade_units,
            'trade_units_route'  => [
                'name'       => 'grp.trade_units.brands.trade_units.index',
                'parameters' => [$this->slug],
            ],
        ];
    }
}
