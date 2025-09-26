<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Sept 2025 12:47:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment;

use App\Http\Resources\Helpers\AddressResource;
use App\Models\Dispatching\DeliveryNote;
use Lorisleiva\Actions\Concerns\AsAction;

class GetShippingDeliveryNoteData
{
    use AsAction;


    public function handle(DeliveryNote $deliveryNote, $cascade = true): array
    {


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

        $toPhone = $deliveryNote->phone ?? '';

        if ($cascade) {
            if (!$toPhone) {
                $toPhone = $customer->phone ?? '';
            }
            if (!$toPhone) {
                $toPhone = $shop->phone;
            }
        }

        // $toPhone='dadasd';

        $toEmail = $deliveryNote->email ?? '';

        if ($cascade) {
            if (!$toEmail) {
                $toEmail = $customer->email;
            }
            if (!$toEmail) {
                $toEmail = $shop->email;
            }
        }


        $toCompanyName = $deliveryNote->company_name ?? '';

        if ($cascade) {
            if (!$toCompanyName) {
                $toCompanyName = $customer->company_name ?? '';
            }
            if (!$toCompanyName) {
                $toCompanyName = $customer->name;
            }
        }



        $contactName   = $deliveryNote->contact_name ?? '';

        if ($cascade) {
            if (!$contactName) {
                $contactName = $customer->contact_name ?? '';
            }
        }


        $toFirstName = '';
        $toLastName = '';

        if ($contactName) {
            $exploded = explode(' ', $contactName);
            $toFirstName = explode(' ', $contactName)[0];
            if (count($exploded) > 1) {
                $toLastName  = (str_contains($contactName, ' '))
                    ? substr($contactName, strpos($contactName, ' ') + 1)
                    : '-';
            }
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
