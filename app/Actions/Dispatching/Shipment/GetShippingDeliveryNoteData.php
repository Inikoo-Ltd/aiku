<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Sept 2025 12:47:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment;

use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetShippingDeliveryNoteData
{
    use AsAction;


    public function handle(DeliveryNote $deliveryNote): array
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

        $toPhone       = $deliveryNote->phone ?? '';
        $toEmail       = $deliveryNote->email ?? '';
        $toCompanyName = $deliveryNote->company_name ?? '';
        $contactName   = $deliveryNote->contact_name ?? '';


        $toFirstName = '';
        $toLastName  = '';

        if ($contactName) {
            $exploded    = explode(' ', $contactName);
            $toFirstName = explode(' ', $contactName)[0];
            if (count($exploded) > 1) {
                $toLastName = (str_contains($contactName, ' '))
                    ? substr($contactName, strpos($contactName, ' ') + 1)
                    : '-';
            }
        }

        $cashOnDelivery = null;
        if ($deliveryNote->is_cash_on_delivery) {
            $order          = $deliveryNote->order;
            $cashOnDelivery = [
                'amount'   => $order->total_amount,
                'currency' => $order->currency->code
            ];
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
            'from_address'       => $this->getAddress($shop->address),
            'to_address'         => $this->getAddress($address),
            'to_contact_name'    => $contactName,
            'to_first_name'      => $toFirstName,
            'to_last_name'       => $toLastName,
            'to_company_name'    => $toCompanyName,
            'to_phone'           => $toPhone,
            'to_email'           => $toEmail,
            'cash_on_delivery'   => $cashOnDelivery,
        ];
    }

    private function getAddress($address): array
    {
        $country = $address->country;

        $addressData = [
            'id'                  => $address->id,
            'address_line_1'      => $address->address_line_1,
            'address_line_2'      => $address->address_line_2,
            'sorting_code'        => $address->sorting_code,
            'postal_code'         => $address->postal_code,
            'locality'            => $address->locality,
            'dependent_locality'  => $address->dependent_locality,
            'administrative_area' => $address->administrative_area,
            'country_code'        => $address->country_code,
            'country_id'          => $address->country_id,
            'country_iso_numeric' => $country->code_iso_numeric,
        ];

        foreach (Arr::except($addressData, ['id', 'country_id']) as $key => $value) {
            $value = trim($value);
            $value = str_replace("'", '', $value);
            $value = str_replace("`", '', $value);
            $value = str_replace('"', '', $value);
            $value = str_replace("&", ' ', $value);
            $value = str_replace("Ø", 'ø', $value);
            $value = str_replace("²", '2', $value);

            $addressData[$key] = $value;
        }

        return $addressData;
    }

}
