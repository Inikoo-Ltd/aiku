<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-15h-10m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Goods\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $shop_slug
 * @property string $department_slug
 * @property string $code
 * @property string $name
 * @property mixed $state
 * @property string $description
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $shop_code
 * @property mixed $shop_name
 * @property int $number_current_families
 * @property int $number_current_products
 */
class MasterFamiliesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'slug'               => $this->slug,
            'code'               => $this->code,
            'name'               => $this->name,
            'state'              => [
                'icon'    => $this->state->stateIcon()[$this->state->value]['icon'],
                'class'   => $this->state->stateIcon()[$this->state->value]['class'],
                'tooltip' => $this->state->labels()[$this->state->value],
            ],
            'description'              => $this->description,
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
        ];
    }
}
