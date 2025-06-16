<?php

/*
 * author Arya Permana - Kirin
 * created on 22-05-2025-11h-24m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

class PackingsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'quantity'            => $this->quantity,
            'engine'              => $this->engine,
            'packer'              => $this->packer->contact_name
        ];
    }
}
