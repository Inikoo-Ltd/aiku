<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 May 2025 12:55:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ShippingDropshippingDeliveryNoteResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var DeliveryNote $deliveryNote */
        $deliveryNote = $this->resource;

        $customer = $deliveryNote->customer;
        $shop     = $deliveryNote->shop;

        $shopContactName = $shop->contact_name;
        if (!$shop->contact_name) {
            $shopContactName = 'A B';
        }

        $shopLastName = (strpos($shopContactName, ' ') !== false)
            ? substr($shopContactName, strpos($shopContactName, ' ') + 1)
            : 'Unknown';


        $address = $deliveryNote->deliveryAddress;

        $recipient = $deliveryNote->customerClient;


        $toCompanyName = $recipient->company_name ?? '';
        $contactName   = $recipient->contact_name ?? '';

        $toFirstName = explode(' ', $contactName)[0];
        $toLastName  = (strpos($contactName, ' ') !== false)
            ? substr($contactName, strpos($contactName, ' ') + 1)
            : '';

        $toPhone = $recipient->phone ?? '';
        $toEmail = $recipient->email ?? '';


        return [
            'id'                 => $deliveryNote->id,
            'customer_reference' => $customer->reference,
            'from_first_name'    => explode(' ', $shopContactName)[0],
            'from_last_name'     => $shopLastName,
            'from_company_name'  => $shop->company_name,
            'from_contact_name'  => $shopContactName,
            'from_phone'         => $shop->phone,
            'from_email'         => $shop->email,
            'from_address'       => AddressResource::make($shop->address)->getArray(),
            'to_address'         => AddressResource::make($address)->getArray(),
            'to_contact_name'    => $contactName,
            'to_first_name'      => $toFirstName,
            'to_last_name'       => $toLastName,
            'to_company_name'    => $toCompanyName,
            'to_phone'           => $toPhone,
            'to_email'           => $toEmail,
        ];
    }
}
