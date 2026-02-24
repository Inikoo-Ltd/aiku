<?php

/*
 * author Louis Perez
 * created on 31-12-2025-09h-32m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Http\Resources\Catalogue;

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
 * @property-read string      $parent_code
 * @property-read string      $parent_slug
 */
class VariantsResource extends JsonResource
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
            'parent_code'                   => $this->parent_code,
            'parent_slug'                   => $this->parent_slug,
            'shop_code'                     => $this->shop?->code,
            'shop_slug'                     => $this->shop?->slug,
            'organisation_slug'             => $this->organisation?->slug,
            'family_slug'                   => $this->family?->slug,
            'product_list'                  => $this->allProduct?->toArray(),
        ];
    }
}
