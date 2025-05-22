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
            'state'               => $this->state,
            'picking'             => PickingsResource::make($this->picking),
            'quantity_packed'     => $this->quantity_packed,
            'engine'              => $this->engine,
            'packer'              => $this->packer->contact_name
        ];
    }
}
