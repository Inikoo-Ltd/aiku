<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\CRM\CustomersResource;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCustomerShowcase
{
    use AsObject;

    public function handle(Customer $customer): array
    {
        $addresses                = [];
        $addressUpdateRoute       = [];
        $canOpenAddressManagement = false;
        if ($customer->shop->type != ShopTypeEnum::DROPSHIPPING) {
            $canOpenAddressManagement = true;
            $addresses                = $this->getAddresses($customer);
            $addressUpdateRoute       = [
                'method'     => 'patch',
                'name'       => 'grp.models.customer.address.update',
                'parameters' => [
                    'customer' => $customer->id
                ]
            ];
        }


        return [
            'customer' => CustomersResource::make($customer)->getArray(),

            'can_open_address_management' => $canOpenAddressManagement,
            'address_update_route'        => $addressUpdateRoute,
            'addresses'                   => $addresses


        ];
    }

    public function getAddresses(Customer $customer): array
    {
        $addresses = $customer->addresses;

        $processedAddresses = $addresses->map(function ($address) {
            if (!DB::table('model_has_addresses')->where('address_id', $address->id)->where('model_type', '=', 'Customer')->exists()) {
                return $address->setAttribute('can_delete', false)
                    ->setAttribute('can_edit', true);
            }


            return $address->setAttribute('can_delete', true)
                ->setAttribute('can_edit', true);
        });

        $customerAddressId         = $customer->address->id;
        $customerDeliveryAddressId = $customer->deliveryAddress->id;
        if ($customer->fulfilmentCustomer) {
            $palletReturnDeliveryAddressIds = PalletReturn::where('fulfilment_customer_id', $customer->fulfilmentCustomer->id)
                ->pluck('delivery_address_id')
                ->unique()
                ->toArray();
        } else {
            $palletReturnDeliveryAddressIds = [];
        }

        $forbiddenAddressIds = array_merge(
            $palletReturnDeliveryAddressIds,
            [$customerAddressId, $customerDeliveryAddressId]
        );

        $processedAddresses->each(function ($address) use ($forbiddenAddressIds) {
            if (in_array($address->id, $forbiddenAddressIds, true)) {
                $address->setAttribute('can_delete', false)
                    ->setAttribute('can_edit', true);
            }
        });

        $addressCollection = AddressResource::collection($processedAddresses);

        return [
            'isCannotSelect'                 => true,
            'address_list'                   => $addressCollection,
            'options'                        => [
                'countriesAddressData' => GetAddressData::run()
            ],
            'pinned_address_id'              => $customer->delivery_address_id,
            'home_address_id'                => $customer->address_id,
            'current_selected_address_id'    => $customer->delivery_address_id,
            'selected_delivery_addresses_id' => $palletReturnDeliveryAddressIds,
            'routes_list'                    => [
                'pinned_route' => [
                    'method'     => 'patch',
                    'name'       => 'grp.models.customer.delivery-address.update',
                    'parameters' => [
                        'customer' => $customer->id
                    ]
                ],
                'delete_route' => [
                    'method'     => 'delete',
                    'name'       => 'grp.models.customer.delivery-address.delete',
                    'parameters' => [
                        'customer' => $customer->id
                    ]
                ],
                'store_route'  => [
                    'method'     => 'post',
                    'name'       => 'grp.models.customer.address.store',
                    'parameters' => [
                        'customer' => $customer->id
                    ]
                ]
            ]
        ];
    }

}
