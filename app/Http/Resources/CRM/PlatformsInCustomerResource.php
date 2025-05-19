<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-09h-29m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\CRM;

use App\Models\Dropshipping\Platform;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $customer_has_platform_id
 * @property mixed $id
 * @property mixed $code
 * @property mixed $name
 * @property mixed $type
 * @property mixed $slug
 * @property mixed $number_portfolios
 * @property mixed $number_customer_clients
 * @property mixed $number_orders
 */
class PlatformsInCustomerResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Platform $platform */
        $platform = Platform::find($this->id);

        return [
            'slug'                     => $this->slug,
            'id'                       => $this->id,
            'code'                     => $this->code,
            'name'                     => $this->name,
            'number_portfolios'        => $this->number_portfolios,
            'number_clients'           => $this->number_customer_clients,
            'number_orders'            => $this->number_orders,
            'type'                     => $this->type,
            'customer_has_platform_id' => $this->customer_has_platform_id,
            'customer_has_platform_slug' => $this->customer_has_platform_slug ?? null,
            'image'                    => $platform->imageSources(48, 48) ?? null,
        ];
    }
}
