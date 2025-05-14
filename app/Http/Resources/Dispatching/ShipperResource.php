<?php
/*
 * author Arya Permana - Kirin
 * created on 14-05-2025-11h-16m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Dispatching;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipperResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'slug'                => $this->slug,
            'code'                => $this->code,
            'name'                => $this->name,
            'contact_name'        => $this->contact_name,
            'company_name'        => $this->company_name,
            'phone'               => $this->phone,
            'website'             => $this->website,
            'tracking_url'        => $this->tracking_url,
        ];
    }
}
