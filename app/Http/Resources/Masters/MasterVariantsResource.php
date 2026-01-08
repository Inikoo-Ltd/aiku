<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-15h-10m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Masters;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int         $id
 * @property-read string      $code
 * @property-read string      $slug
 * @property-read int         $leader_id
 * @property-read int         $number_minions
 * @property-read int         $number_dimensions
 * @property-read int         $number_used_slots
 * @property-read int         $number_used_slots_for_sale
 * @property-read array       $data
 * @property-read int         $leader_product_id
 * @property-read string      $leader_product_name
 * @property-read string      $leader_product_code
 * @property-read string      $leader_product_slug
 */
class MasterVariantsResource extends JsonResource
{
    public function toArray($request): array
    {
        $options = data_get($this->data, 'variants.*.options');

        return [
            'id'                            => $this->id,
            'code'                          => $this->code,
            'slug'                          => $this->slug,
            'leader_id'                     => $this->leader_id,
            'number_minions'                => $this->number_minions,
            'number_dimensions'             => $this->number_dimensions,
            'number_used_slots'             => $this->number_used_slots,
            'number_max_slots'              => count($options[0] ?? [1]) * count($options[1] ?? [1]),
            'number_used_slots_for_sale'    => $this->number_used_slots_for_sale,
            'data'                          => $this->data,
            'options'                       => data_get($this->data, 'variants') ? collect(data_get($this->data, 'variants'))->mapWithKeys(fn ($variant) => [$variant['label'] => json_encode($variant['options'])]) : [],
            'leader_product_id'             => $this->leader_product_id,
            'leader_product_name'           => $this->leader_product_name,
            'leader_product_code'           => $this->leader_product_code,
            'leader_product_slug'           => $this->leader_product_slug,
        ];
    }
}
