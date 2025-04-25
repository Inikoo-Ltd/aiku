<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-13h-36m
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
 */
class MasterShopResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {


        return [
            'slug'               => $this->slug,
            'code'               => $this->code,
            'name'               => $this->name,
        ];
    }
}
