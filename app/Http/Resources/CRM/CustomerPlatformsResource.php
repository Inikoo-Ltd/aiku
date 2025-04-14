<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-09h-29m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\CRM;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerPlatformsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'customer_has_platform_id' => $this->customer_has_platform_id,
            'id'                    => $this->id,
            'code'                  => $this->code,
            'name'                  => $this->name,
            'type'                  => $this->type
        ];
    }
}
