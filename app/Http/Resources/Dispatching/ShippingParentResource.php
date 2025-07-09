<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 May 2025 12:55:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ShippingParentResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var DeliveryNote|PalletReturn $parent */
        $parent = $this->resource;

        $customer = $parent instanceof DeliveryNote ? $parent->customer : $parent->fulfilmentCustomer->customer;
        $shop     = $parent instanceof DeliveryNote ? $parent->shop : $parent->fulfilment->shop;

        // dd($customer->contact_name);
        $shopContactName = $shop->contact_name;
        if (!$shop->contact_name) {
            $shopContactName = 'A B';
        }

        $shopLastName = (strpos($shopContactName, ' ') !== false)
            ? substr($shopContactName, strpos($shopContactName, ' ') + 1)
            : 'Unknown';


        $address = $parent->deliveryAddress;
        $toCompanyName = 'Unknown'; // TODO: fill this to corresponding customer company name
        $toContactName = '';
        if ($parent instanceof DeliveryNote) {
            $contactName = $parent->deliveryAddress->contact_name; //todo if if Dropshippin or not
            if (!$contactName) {
                $contactName = $customer->contact_name;
            }
            $toFirstName = explode(' ', $contactName)[0];
            $toLastName  = (strpos($contactName, ' ') !== false)
                ? substr($contactName, strpos($contactName, ' ') + 1)
                : 'Unknown';
            $toContactName = $contactName;
            $toPhone = ''; // todo
            $toEmail = ''; // todo

        } else {
            $isManual = $parent->platform?->type == PlatformTypeEnum::MANUAL;
            if ($isManual) {
                $customer = $parent->customerSaleChannel->customer;
                $contactName = $customer->contact_name;
                $toFirstName = explode(' ', $contactName)[0];
                $toLastName  = (strpos($contactName, ' ') !== false)
                    ? substr($contactName, strpos($contactName, ' ') + 1)
                    : 'Unknown';
                $toContactName = $contactName;
                $toPhone = ''; // todo
                $toEmail = ''; // todo

            } else {
                $toFirstName = Arr::get($parent->data, 'destination.first_name', 'Unknown');
                $toLastName = Arr::get($parent->data, 'destination.last_name', 'Unknown');
                if ($toFirstName != 'Unknown' && $toLastName != 'Unknown') {
                    $toContactName = $toFirstName . ' ' . $toLastName;
                } else {
                    $toContactName = Arr::get($parent->data, 'destination.contact_name', 'Unknown');
                }
                $toEmail = Arr::get($parent->data, 'destination.email') ?? $shop->email;
                $toPhone = Arr::get($parent->data, 'destination.phone') ?? $shop->phone;
            }
        }

        return [
            'id'                 => $parent->id,
            'customer_reference' => $customer->reference,
            'from_first_name'    => explode(' ', $shopContactName)[0],
            'from_last_name'     => $shopLastName,
            'from_company_name'  => $shop->company_name,
            'from_contact_name'   => $shopContactName,
            'from_phone'         => $shop->phone,
            'from_email'         => $shop->email,
            'from_address'       => AddressResource::make($shop->address)->getArray(),
            'to_address'         => AddressResource::make($address)->getArray(),
            'to_contact_name'    => $toContactName,
            'to_first_name'    => $toFirstName,
            'to_last_name'     => $toLastName,
            'to_company_name'  => $toCompanyName,
            'to_phone'         => $toPhone,
            'to_email'         => $toEmail,
        ];
    }
}
