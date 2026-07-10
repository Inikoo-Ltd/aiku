<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 May 2026 11:18:32 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM\Customer;

use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Models\CRM\Customer;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class CustomerSearchResultResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var Customer $customer */
        $customer = $this;
        return [
            'id'                        => $customer->id,
            'slug'                      => $customer->slug,
            'name'                      => $customer->name,
            'location'                  => $customer->location,
            'state_icon'                => CustomerStateEnum::stateIcon()[$customer->state->value],
            'contact_name'              => $customer->contact_name,
            'company_name'              => $customer->company_name,
            'email'                     => $customer->email,
            'phone'                     => $customer->phone,
            'average_order_value'       => '$average_order_value', // TODO


        ];
    }
}
