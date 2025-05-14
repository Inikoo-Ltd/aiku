<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Api;

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
            'id'                     => $customer->id,
            'slug'                   => $customer->slug,
            'reference'              => $customer->reference,
            'name'                   => $customer->name,
            'contact_name'           => $customer->contact_name,
            'company_name'           => $customer->company_name,
            'location'               => $customer->location,
            'email'                  => $customer->email,
            'phone'                  => $customer->phone,
            'created_at'             => $customer->created_at,
            'updated_at'             => $customer->updated_at,
        ];
    }
}
