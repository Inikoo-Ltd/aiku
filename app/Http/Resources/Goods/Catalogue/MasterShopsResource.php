<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-10h-31m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Goods\Catalogue;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $slug
 * @property string $name
 * @property int $departments
 * @property int $families
 * @property int $products
 * @property int $used_in
 */
class MasterShopsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'slug'        => $this->slug,
            'code'        => $this->code,
            'name'        => $this->name,
            'used_in'     => $this->used_in,
            'departments' => $this->departments,
            'families'    => $this->families,
            'products'    => $this->products,

        ];
    }
}
