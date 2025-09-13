<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 18 Apr 2024 09:27:56 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Enums\Ordering\ShippingZoneSchema\ShippingZoneSchemaStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingZoneSchemasResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                       => $this->id,
            'slug'                     => $this->slug,
            'state_icon'               => ShippingZoneSchemaStateEnum::stateIcon()[$this->state->value],
            'name'                     => $this->name,
            'created_at'               => $this->created_at,
            'number_customers'         => $this->number_customers,
            'number_orders'            => $this->number_orders,
            'zones'                    => $this->number_shipping_zones,
            'amount'                   => $this->amount,
            'first_used'               => $this->first_used_at,
            'last_used'                => $this->last_used_at,
            'organisation_name'     => $this->organisation_name,
            'currency_code'             => $this->currency_code,
            'organisation_slug' => $this->organisation_slug,
            'shop_name'         => $this->shop_name,
            'shop_slug'         => $this->shop_slug,
        ];
    }
}
