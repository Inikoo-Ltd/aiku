<?php

/*
 * author Arya Permana - Kirin
 * created on 17-03-2025-13h-46m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\UI\Dashboard;

use App\Http\Resources\CRM\CustomerResource;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRetinaB2BHomeData
{
    use AsObject;

    public function handle(Customer $customer): array
    {
        return [
            'customer'        => CustomerResource::make($customer)->getArray(),
            'status'          => $customer->status,
            'additional_data' => $customer->data,
            'addresses'       => [
                'isCannotSelect'              => true,
                'pinned_address_id'           => $customer->delivery_address_id,
                'home_address_id'             => $customer->address_id,
                'current_selected_address_id' => $customer->delivery_address_id,
                'routes_list'                 => [
                    'pinned_route' => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.customer.delivery-address.update',
                        'parameters' => [
                            'customer' => $customer->id
                        ]
                    ],
                ]
            ],
            'currency_code'   => $customer->shop->currency->code,
        ];
    }
}
