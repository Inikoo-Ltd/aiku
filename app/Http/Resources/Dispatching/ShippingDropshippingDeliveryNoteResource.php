<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 May 2025 12:55:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingDropshippingDeliveryNoteResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var DeliveryNote $deliveryNote */
        $deliveryNote = $this->resource;

        $customer = $deliveryNote->customer;
        $shop     = $deliveryNote->shop;


        $fromCompany = $customer->company_name;
        if (!$fromCompany) {
            $fromCompany = $customer->name;
        }
        if (!$fromCompany) {
            $fromCompany = $shop->name;
        }

        $fromContactName = $customer->contact_name;
        if (!$fromContactName) {
            $fromContactName = $customer->name;
        }


        $fromLastName = (str_contains($fromContactName, ' '))
            ? substr($fromContactName, strpos($fromContactName, ' ') + 1)
            : 'Unknown';

        $fromFirstName = explode(' ', $fromContactName)[0];

        $fromPhone = $customer->phone;
        if (!$fromPhone) {
            $fromPhone = $shop->phone;
        }

        $fromEmail = $customer->email;
        if (!$fromEmail) {
            $fromEmail = $shop->email;
        }


        $address = $deliveryNote->deliveryAddress;

        $recipient = $deliveryNote->customerClient;

        $toCompanyName = $recipient->company_name ?? '';
        $contactName   = $recipient->contact_name ?? '';

        $toFirstName = explode(' ', $contactName)[0];
        $toLastName  = (str_contains($contactName, ' '))
            ? substr($contactName, strpos($contactName, ' ') + 1)
            : '-';

        $toPhone = $recipient->phone ?? '';
        $toEmail = $recipient->email ?? '';

        // if from shopify/ebay/etc
        if ($toCompanyName === '') {
            $platform = $recipient?->salesChannel?->platform_user_type;
            if ($platform) {
                $toCompanyName = $recipient->contact_name ?? '';
            }
        }

        if ($toEmail == '') {
            $toEmail = $shop->email;
        }

        return [
            'id'                 => $deliveryNote->id,
            'customer_reference' => $deliveryNote->reference,
            'from_first_name'    => $fromFirstName,
            'from_last_name'     => $fromLastName,
            'from_company_name'  => $fromCompany,
            'from_contact_name'  => $fromContactName,
            'from_phone'         => $fromPhone,
            'from_email'         => $fromEmail,
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
