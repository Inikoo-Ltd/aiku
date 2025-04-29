<?php

/*
 * author Arya Permana - Kirin
 * created on 17-03-2025-13h-46m
 * github: https://github.com/KirinZero0
 * copyright 2025
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

class GetRetinaB2BHomeData
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
            'addresses'   => [
                'isCannotSelect'                => true,
                // 'address_list'                  => $addressCollection,
                // 'options'                       => [
                //     'countriesAddressData' => GetAddressData::run()
                // ],
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
            'currency_code' => $customer->shop->currency->code,
        ];
    }
}
