<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\UI\Dashboard;

use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Http\Resources\CRM\CustomerClientResource;

class GetRetinaDropshippingHomeData
{
    use AsObject;

    public function handle(Customer $customer, ActionRequest $request): array
    {
        $irisDomain = $customer->shop?->website?->domain;

        // $processedAddresses = $addresses->map(function ($address) {


        //     if (!DB::table('model_has_addresses')->where('address_id', $address->id)->where('model_type', '=', 'Customer')->exists()) {

        //         return $address->setAttribute('can_delete', false)
        //             ->setAttribute('can_edit', true);
        //     }


        //     return $address->setAttribute('can_delete', true)
        //                     ->setAttribute('can_edit', true);
        // });

        // $customerAddressId              = $fulfilmentCustomer->customer->address->id;
        // $customerDeliveryAddressId      = $fulfilmentCustomer->customer->deliveryAddress->id;
        // $palletReturnDeliveryAddressIds = PalletReturn::where('fulfilment_customer_id', $fulfilmentCustomer->id)
        //                                     ->pluck('delivery_address_id')
        //                                     ->unique()
        //                                     ->toArray();

        // $forbiddenAddressIds = array_merge(
        //     $palletReturnDeliveryAddressIds,
        //     [$customerAddressId, $customerDeliveryAddressId]
        // );

        // $processedAddresses->each(function ($address) use ($forbiddenAddressIds) {
        //     if (in_array($address->id, $forbiddenAddressIds, true)) {
        //         $address->setAttribute('can_delete', false)
        //                 ->setAttribute('can_edit', true);
        //     }
        // });

        // $addressCollection = AddressResource::collection($processedAddresses);


        return [
            'customer'          => CustomerResource::make($customer)->getArray(),
            'status'                => $customer->status,
            'additional_data'       => $customer->data,
            'address_update_route'  => [
                'method'     => 'patch',
                'name'       => 'grp.models.fulfilment-customer.address.update',
                'parameters' => [
                    'fulfilmentCustomer' => $customer->id
                ]
            ],
            'addresses'   => [
                'isCannotSelect'                => true,
                // 'address_list'                  => $addressCollection,
                'options'                       => [
                    'countriesAddressData' => GetAddressData::run()
                ],
                'pinned_address_id'              => $customer->delivery_address_id,
                'home_address_id'                => $customer->address_id,
                'current_selected_address_id'    => $customer->delivery_address_id,
                // 'selected_delivery_addresses_id' => $palletReturnDeliveryAddressIds,
                'routes_list'                    => [
                    'pinned_route'                   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.customer.delivery-address.update',
                        'parameters' => [
                            'customer' => $customer->id
                        ]
                    ],
                ]
            ],
            'clients'  => $customer->clients ? CustomerClientResource::collection($customer->clients) : [],
            'currency_code' => $customer->shop->currency->code,
        ];
    }
}
