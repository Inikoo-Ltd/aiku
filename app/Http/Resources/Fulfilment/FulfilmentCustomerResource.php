<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Feb 2024 19:57:44 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\Helpers\TaxNumberResource;
use App\Models\Fulfilment\FulfilmentCustomer;
use Illuminate\Http\Resources\Json\JsonResource;

class FulfilmentCustomerResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var FulfilmentCustomer $fulfilmentCustomer */
        $fulfilmentCustomer = $this;

        return [
            'radioTabs'                     => [
                'pallets_storage' => $fulfilmentCustomer->pallets_storage,
                'items_storage'   => $fulfilmentCustomer->items_storage,
                // 'dropshipping'    => $fulfilmentCustomer->dropshipping,
                'space_rental'    => $fulfilmentCustomer->space_rental,
            ],
            'number_pallets'                => $fulfilmentCustomer->number_pallets,
            'number_pallets_state_received' => $fulfilmentCustomer->number_pallets_state_received,
            'number_stored_items'           => $fulfilmentCustomer->number_stored_items,
            'number_pallet_deliveries'      => $fulfilmentCustomer->number_pallet_deliveries,
            'number_pallet_returns'         => $fulfilmentCustomer->number_pallet_returns,
            'slug'                          => $fulfilmentCustomer->slug,
            'fulfilment'                    => [
                'slug' => $fulfilmentCustomer->fulfilment->slug,
                'name' => $fulfilmentCustomer->fulfilment->shop->name,
            ],
            'customer'                      => [
                'id'           => $fulfilmentCustomer->customer->id,
                'slug'         => $fulfilmentCustomer->customer->slug,
                'reference'    => $fulfilmentCustomer->customer->reference,
                'name'         => $fulfilmentCustomer->customer->name,
                'contact_name' => $fulfilmentCustomer->customer->contact_name,
                'company_name' => $fulfilmentCustomer->customer->company_name,
                'location'     => $fulfilmentCustomer->customer->location,
                'address'      => AddressResource::make($fulfilmentCustomer->customer->address),
                'email'        => $fulfilmentCustomer->customer->email,
                'phone'        => $fulfilmentCustomer->customer->phone,
                'created_at'   => $fulfilmentCustomer->customer->created_at,
                'status'       => $fulfilmentCustomer->customer->status,
                'approved_at'  => $fulfilmentCustomer->customer->approved_at ?? null,
                'tax_number'   => $fulfilmentCustomer->customer->taxNumber ? TaxNumberResource::make($fulfilmentCustomer->customer->taxNumber)->getArray() : [],

            ]

        ];
    }
}
