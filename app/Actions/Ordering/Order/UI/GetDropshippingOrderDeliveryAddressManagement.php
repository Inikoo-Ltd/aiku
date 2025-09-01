<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Jul 2025 13:06:21 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDropshippingOrderDeliveryAddressManagement
{
    use AsObject;

    public function handle(Order $order, bool $isRetina = false): array
    {

        $modelRoutePrefix =  $isRetina ? 'retina.models.' : 'grp.models.';

        $addresses = Collect([$order->deliveryAddress]);



        $processedAddresses = $addresses->map(function ($address) {
            if (!DB::table('model_has_addresses')->where('address_id', $address->id)->where('model_type', '=', 'Customer')->exists()) {
                return $address->setAttribute('can_delete', false)
                    ->setAttribute('can_edit', true);
            }


            return $address->setAttribute('can_delete', true)
                ->setAttribute('can_edit', true);
        });

        $customerAddressId         = $order->customer->address->id;
        $customerDeliveryAddressId = $order->customer->deliveryAddress->id;
        $orderDeliveryAddressIds   = Order::where('customer_id', $order->customer_id)
            ->pluck('delivery_address_id')
            ->unique()
            ->toArray();

        $forbiddenAddressIds = array_merge(
            $orderDeliveryAddressIds,
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
            'address_update_route' => [
                'method'     => 'patch',
                'name'       => $modelRoutePrefix.'order.delivery_address_update',
                'parameters' => [
                    'order' => $order->id
                ]
            ],

            'addresses'            => [
                'isCannotSelect'                 => true,
                'address_list'                   => $addressCollection,
                'options'                        => [
                    'countriesAddressData' => GetAddressData::run()
                ],
                'pinned_address_id'              => $order->customer->delivery_address_id,
                'home_address_id'                => $order->customer->address_id,
                'current_selected_address_id'    => $order->delivery_address_id,
                'collection_address_id'          => $order->collection_address_id,
                'selected_delivery_addresses_id' => $orderDeliveryAddressIds,
                'routes_list'                    => [
                    'switch_route' => [
                        'method'     => 'patch',
                        'name'       => $modelRoutePrefix.'order.address.switch',
                        'parameters' => [
                            'order' => $order->id
                        ]
                    ],
                    'pinned_route'                   => [
                        'method'     => 'patch',
                        'name'       => $modelRoutePrefix.'customer.delivery-address.update',
                        'parameters' => [
                            'customer' => $order->customer_id
                        ]
                    ],
                    'delete_route' => [
                        'method'     => 'delete',
                        'name'       => $modelRoutePrefix.'customer.delivery-address.delete',
                        'parameters' => [
                            'customer' => $order->customer_id
                        ]
                    ],
                    'store_route'  => [
                        'method'     => 'post',
                        'name'       => $modelRoutePrefix.'customer.address.store',
                        'parameters' => [
                            'customer' => $order->customer_id
                        ]
                    ]
                ]
            ],
            'address_modal_title'  => __('Delivery address for Order') . ' #'.$order->reference,
        ];
    }
}
