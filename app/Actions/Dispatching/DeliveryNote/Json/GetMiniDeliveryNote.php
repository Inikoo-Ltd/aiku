<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:48:15 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\Json;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\OrgAction;
use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Dispatching\ShipmentsResource;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Helpers\Address;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMiniDeliveryNote extends OrgAction
{
    use AsAction;
    use GetPlatformLogo;

    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        return $deliveryNote;
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request)
    {
        $this->initialisationFromWarehouse($deliveryNote->warehouse, $request);

        return $this->handle($deliveryNote);
    }

    public function jsonResponse(DeliveryNote $deliveryNote): array
    {
        $estWeight = ($deliveryNote->estimated_weight ?? 0) / 1000;
        $order     = $deliveryNote->orders->first();
        
        return [
            'delivery_note' => [
                'state'            => $deliveryNote->state,
                'state_icon'       => DeliveryNoteStateEnum::stateIcon()[$deliveryNote->state->value],
                'state_label'      => $deliveryNote->state->labels()[$deliveryNote->state->value],
                'customer'         => array_merge(
                    CustomerResource::make($deliveryNote->customer)->getArray(),
                    [
                        'addresses' => [
                            'delivery' => AddressResource::make($deliveryNote->deliveryAddress ?? new Address()),
                        ],
                        'route'     => [
                            'name'       => 'grp.org.shops.show.crm.customers.show',
                            'parameters' => [
                                'organisation' => $deliveryNote->organisation->slug,
                                'shop'         => $deliveryNote->shop->slug,
                                'customer'     => $deliveryNote->customer->slug
                            ]
                        ]
                    ]
                ),
                'customer_client'  => $deliveryNote->customerClient,
                'platform'         => [
                    'name' => $deliveryNote->platform?->name,
                    'logo' => $deliveryNote->customerSalesChannel?->platform?->code ? $this->getPlatformLogo($deliveryNote->customerSalesChannel->platform->code) : null,
                ],
                'products'         => [
                    'estimated_weight' => $estWeight,
                    'number_items'     => $deliveryNote->number_items,
                ],
                'order'            => [
                    'reference' => $order->reference,
                    'route'     => [
                        'name'       => 'grp.org.shops.show.ordering.orders.show',
                        'parameters' => [
                            'organisation' => $order->organisation->slug,
                            'shop'         => $order->shop->slug,
                            'order'        => $order->slug
                        ]
                    ],
                ],
                'address' => [
                    'delivery' => AddressResource::make($deliveryNote->deliveryAddress ?? new Address()),
                    'options'  => [
                        'countriesAddressData' => GetAddressData::run()
                    ]
                ],
                'delivery_address' => AddressResource::make($deliveryNote->deliveryAddress),
                'picker'           => $deliveryNote->pickerUser,
                'packer'           => $deliveryNote->packerUser,
                'parcels'          => $deliveryNote->parcels,
            ],
        ];
    }
}
