<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-11h-52m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Masters;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $slug
 * @property string $name
 * @property mixed $master_shop_slug
 * @property mixed $master_shop_code
 * @property mixed $master_shop_name
 * @property mixed $master_department_slug
 * @property mixed $master_department_code
 * @property mixed $master_department_name
 * @property mixed $master_family_slug
 * @property mixed $master_family_code
 * @property mixed $master_family_name
 */
class MasterProductResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'slug'                   => $this->slug,
            'code'                   => $this->code,
            'name'                   => $this->name
        ];
    }
}
