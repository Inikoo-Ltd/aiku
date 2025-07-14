<?php

/*
 * author Arya Permana - Kirin
 * created on 14-05-2025-10h-56m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property string|null $trade_as
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $tracking_url
 * @property string|null $api_shipper
 * @property string|null $label
 */
class ShippersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'slug'                => $this->slug,
            'code'                => $this->code,
            'name'                => $this->name,
            'trade_as'            => $this->trade_as,
            'phone'               => $this->phone,
            'website'             => $this->website,
            'tracking_url'        => $this->tracking_url,
            'api_shipper'         => $this->api_shipper,
            'label'               => $this->label,
        ];
    }
}
