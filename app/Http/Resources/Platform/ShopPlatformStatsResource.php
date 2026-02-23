<?php

/*
 * author Arya Permana - Kirin
 * created on 02-04-2025-15h-09m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Platform;

use Illuminate\Http\Resources\Json\JsonResource;

class ShopPlatformStatsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'code'             => $this->code,
            'slug'             => $this->slug,
            'name'             => $this->name,
            'type'             => $this->type,
            'channels'         => $this->channels ?? 0,
            'customers'        => $this->customers ?? 0,
            'portfolios'       => $this->portfolios ?? 0,
            'customer_clients' => $this->customer_clients ?? 0,
            'sales'            => $this->sales ?? 0,
        ];
    }
}
