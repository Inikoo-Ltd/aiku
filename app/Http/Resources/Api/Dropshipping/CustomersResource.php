<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Jun 2024 13:23:58 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Api\Dropshipping;

use App\Http\Resources\HasSelfCall;
use App\Models\CRM\Customer;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomersResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Customer $customer */
        $customer = $this;

        return [
            'id' => $customer->id,
            'slug' => $customer->slug,
            'reference' => $customer->reference,
            'name' => $customer->name,
            'contact_name' => $customer->contact_name,
            'company_name' => $customer->company_name,
            'location' => $customer->location,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'created_at' => $customer->created_at,
            'updated_at' => $customer->updated_at,
        ];
    }
}
