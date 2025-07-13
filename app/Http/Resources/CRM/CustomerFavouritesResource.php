<?php

/*
 * author Arya Permana - Kirin
 * created on 14-10-2024-10h-32m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\CRM;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $code
 * @property mixed $slug
 * @property mixed $name
 * @property mixed $description
 * @property mixed $price
 */
class CustomerFavouritesResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'code'                   => $this->code,
            'slug'                   => $this->slug,
            'name'                   => $this->name,
            'description'            => $this->description,
            'price'                  => $this->price,
        ];
    }
}
