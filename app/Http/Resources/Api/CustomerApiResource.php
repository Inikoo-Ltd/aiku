<?php

/*
 * author Arya Permana - Kirin
 * created on 23-06-2025-13h-29m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Api;

use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\CRM\Customer;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $number_current_customer_clients
 */
class CustomerApiResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Customer $customer */
        $customer = $this;
        return [
            'slug'                   => $customer->slug,
            'reference'              => $customer->reference,
            'name'                   => $customer->name,
            'contact_name'           => $customer->contact_name,
            'company_name'           => $customer->company_name,
            'location'               => $customer->location,
            'address'                => AddressResource::make($customer->address),
            'email'                  => $customer->email,
            'phone'                  => $customer->phone,
            'created_at'             => $customer->created_at,
            'number_current_customer_clients' => $this->number_current_customer_clients,
        ];
    }
}
