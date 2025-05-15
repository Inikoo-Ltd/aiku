<?php
/*
 * author Arya Permana - Kirin
 * created on 14-05-2025-10h-56m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

class ShippersResource extends JsonResource
{
    public function toArray($request): array
    {
        // dd($this);
        return [
            'id'                  => $this->id,
            'slug'                => $this->slug,
            'code'                => $this->code,
            'name'                => $this->name,
            'phone'               => $this->phone,
            'website'             => $this->website,
            'tracking_url'        => $this->tracking_url,
            'api_shipper'         => $this->api_shipper,
        ];
    }
}
