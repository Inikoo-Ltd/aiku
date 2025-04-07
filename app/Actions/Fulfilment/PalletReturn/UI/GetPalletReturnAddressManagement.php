<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Apr 2025 12:55:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPalletReturnAddressManagement
{
    use AsObject;

    public function handle(PalletReturn $palletReturn, bool $forRetina = false): array
    {
        $addresses = $palletReturn->fulfilmentCustomer->customer->addresses;

        $processedAddresses = $addresses->map(function ($address) {
            if (!DB::table('model_has_addresses')->where('address_id', $address->id)->where('model_type', '=', 'Customer')->exists()) {
                return $address->setAttribute('can_delete', false)
                    ->setAttribute('can_edit', true);
            }


            return $address->setAttribute('can_delete', true)
                ->setAttribute('can_edit', true);
        });

        $customerAddressId              = $palletReturn->fulfilmentCustomer->customer->address->id;
        $customerDeliveryAddressId      = $palletReturn->fulfilmentCustomer->customer->deliveryAddress->id;
        $palletReturnDeliveryAddressIds = PalletReturn::where('fulfilment_customer_id', $palletReturn->fulfilment_customer_id)
            ->pluck('delivery_address_id')
            ->unique()
            ->toArray();

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
            'updateRoute'          => [
                'name'       => $forRetina ? 'retina.models.pallet-return.update' : 'grp.models.pallet-return.update',
                'parameters' => [
                    'palletReturn' => $palletReturn->id
                ]
            ],
            'address_update_route' => [
                'method'     => 'patch',
                'name'       => $forRetina ? 'retina.models.customer.address.update' : 'grp.models.customer.address.update',
                'parameters' => [
                    'customer' => $palletReturn->fulfilmentCustomer->customer_id
                ]
            ],
            'addresses'            => [
                'isCannotSelect'                 => true,
                'address_list'                   => $addressCollection,
                'options'                        => [
                    'countriesAddressData' => GetAddressData::run()
                ],
                'pinned_address_id'              => $palletReturn->fulfilmentCustomer->customer->delivery_address_id,
                'home_address_id'                => $palletReturn->fulfilmentCustomer->customer->address_id,
                'current_selected_address_id'    => $palletReturn->delivery_address_id,
                'selected_delivery_addresses_id' => $palletReturnDeliveryAddressIds,
                'routes_list'                    => [
                    'switch_route' => [
                        'method'     => 'patch',
                        'name'       => $forRetina ? 'retina.models.pallet-return.address.switch' : 'grp.models.pallet-return.address.switch',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id
                        ]
                    ],
                    'pinned_route' => [
                        'method'     => 'patch',
                        'name'       => $forRetina ? 'retina.models.customer.delivery-address.update' : 'grp.models.customer.delivery-address.update',
                        'parameters' => [
                            'customer' => $palletReturn->fulfilmentCustomer->customer_id
                        ]
                    ],
                    'delete_route' => [
                        'method'     => 'delete',
                        'name'       => $forRetina ? 'retina.models.customer.delivery-address.delete' : 'grp.models.customer.delivery-address.delete',
                        'parameters' => [
                            'customer' => $palletReturn->fulfilmentCustomer->customer_id
                        ]
                    ],
                    'store_route'  => [
                        'method'     => 'post',
                        'name'       => $forRetina ? 'retina.models.customer.delivery-address.store' : 'grp.models.customer.address.store',
                        'parameters' => [
                            'customer' => $palletReturn->fulfilmentCustomer->customer_id
                        ]
                    ]
                ]
            ],
            'address_modal_title'  => __('Delivery address for').' '.$palletReturn->reference,
        ];
    }


    public function boxStatsAddressData(PalletReturn $palletReturn, bool $forRetina = false): array
    {
        return [
            'address' => [
                'value'            => $palletReturn->is_collection ? null : AddressResource::make($palletReturn->deliveryAddress),
                'options'          => [
                    'countriesAddressData' => GetAddressData::run()
                ],
                'address_customer' => [
                    'value'   => AddressResource::make($palletReturn->fulfilmentCustomer->customer->address),
                    'options' => [
                        'countriesAddressData' => GetAddressData::run()
                    ],
                ],
                'routes_address'   => $forRetina
                    ? [
                        'store'  => [
                            'method'     => 'post',
                            'name'       => 'retina.models.pallet-return.address.store',
                            'parameters' => [
                                'palletReturn' => $palletReturn->id
                            ]
                        ],
                        'delete' => [
                            'method'     => 'delete',
                            'name'       => 'retina.models.pallet-return.address.delete',
                            'parameters' => [
                                'palletReturn' => $palletReturn->id
                            ]
                        ],
                        'update' => [
                            'method'     => 'patch',
                            'name'       => 'retina.models.pallet-return.address.update',
                            'parameters' => [
                                'palletReturn' => $palletReturn->id
                            ]
                        ]
                    ]
                    : [
                        'store'  => [
                            'method'     => 'post',
                            'name'       => 'grp.models.pallet-return.address.store',
                            'parameters' => [
                                'palletReturn' => $palletReturn->id
                            ]
                        ],
                        'delete' => [
                            'method'     => 'delete',
                            'name'       => 'grp.models.pallet-return.address.delete',
                            'parameters' => [
                                'palletReturn' => $palletReturn->id
                            ]
                        ],
                        'update' => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.pallet-return.address.update',
                            'parameters' => [
                                'palletReturn' => $palletReturn->id
                            ]
                        ]
                    ]
            ],
        ];
    }

}
