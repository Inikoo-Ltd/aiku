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
 * @property mixed $id
 * @property string $code
 */
class MasterVariantsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                            => $this->id,
            'code'                          => $this->code,
            'slug'                          => $this->slug,
            'leader_id'                     => $this->leader_id,
            'number_minions'                => $this->number_minions,
            'number_dimensions'             => $this->number_dimensions,
            'number_used_slots'             => $this->number_used_slots,
            'number_used_slots_for_sale'    => $this->number_used_slots_for_sale,
            'data'                          => $this->data,
            'leader_product_id'             => $this->leader_product_id,
            'leader_product_name'           => $this->leader_product_name,
            'leader_product_code'           => $this->leader_product_code,
            'leader_product_slug'           => $this->leader_product_slug,
        ];
    }
}
