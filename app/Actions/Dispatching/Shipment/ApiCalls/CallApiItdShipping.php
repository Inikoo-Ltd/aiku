<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment\ApiCalls;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\Dispatching\Shipper;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CallApiItdShipping extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function getAccessToken(Shipper $shipper): string
    {
        return Arr::get($shipper->settings, 'access_token');
    }

    public function getBaseUrl(Shipper $shipper): string
    {
        return Arr::get($shipper->settings, 'base_url');
    }

    public function handle(DeliveryNote $parent, Shipper $shipper): array
    {
        $url = 'api/v1/shipments';
        $headers = [
            [
                'Authorization' => 'Bearer ' . $this->getAccessToken($shipper),
                'Content-Type' => 'application/json'
            ]
        ];

        $packages = [
            [
                'packageType' => 3,
                'packageLength' => 30,
                'packageWidth' => 30,
                'packageHeight' => 30,
                'packageWeight' => 1000
            ]
        ];

        $params = [
            'labelSize' => '4x6',
            'doNotUseWebhook' => true,
            'shipments' => [
                [
                    'serviceId' => Arr::get($shipper->data, 'service_id'),
                    'evriService' => [
                        'signature' => false,
                    ],
                    'warehouseName' => $parent->warehouse->name,
                    'invoiceDate' => $parent->date,
                    'invoiceNumber' => $parent->reference,
                    'orderNumber' => $parent->reference,
                    'customerReference' => $parent->customer_reference,
                    'reasonForExport' => 'Gift',
                    'UKIMSNumber' => 'XIUKIM123456789',
                    'fromAddressFirstName' => $parent->fulfilment->shop->name,
                    'fromAddressLastName' => 'Department',
                    'fromAddressCompany' => $parent->fulfilment->shop->company_name,
                    'fromAddressPhone' => $parent->fulfilment->shop->phone,
                    'fromAddressEmail' => $parent->fulfilment->shop->email,
                    'fromAddressStreet1' => $parent->fulfilment->shop->address->address_line_1,
                    'fromAddressStreet2' => $parent->fulfilment->shop->address->address_line_2,
                    'fromAddressStreet3' => null,
                    'fromAddressCity' => $parent->fulfilment->shop->address->dependent_locality,
                    'fromAddressCountyState' => $parent->fulfilment->shop->address->administrative_area,
                    'fromAddressZip' => $parent->fulfilment->shop->address->postal_code,
                    'fromAddressCountryIso' => $parent->fulfilment->shop->address->country_code,
                    'fromAddressEoriNumber' => 'EO123',
                    'fromAddressVatNumber' => Arr::get($parent->fulfilment->shop->data, 'vat_number'),
                    'toAddressFirstName' => $parent->customer->name,
                    'toAddressLastName' => '',
                    'toAddressCompany' => $parent->customer->company_name,
                    'toAddressPhone' => $parent->customer->phone,
                    'toAddressEmail' => $parent->customer->email,
                    'toAddressStreet1' => $parent->customer->address->address_line_1,
                    'toAddressStreet2' => $parent->customer->address->address_line_2,
                    'toAddressStreet3' => null,
                    'toAddressCity' => $parent->customer->address->dependent_locality,
                    'toAddressCountyState' => $parent->customer->address->administrative_area,
                    'toAddressZip' => $parent->customer->address->postal_code,
                    'toAddressCountryIso' => $parent->customer->address->postal_code,
                    'toAddressEoriNumber' => '',
                    'toAddressVatNumber' => Arr::get($parent->customer->data, 'vat_number'),
                    'packages' => $packages
                ]
            ]
        ];

        return ProsesApiCalls::make()->action($this->getBaseUrl($shipper) . $url, $headers, json_encode($params));
    }

    public function rules(): array
    {
        return [
            'reference' => ['required', 'max:64', 'string']
        ];
    }

    public function action(DeliveryNote $deliveryNote, Shipper $shipper, array $modelData): Shipment
    {
        $this->initialisation($deliveryNote->organisation, $modelData);

        return $this->handle($deliveryNote, $shipper, $this->validatedData);
    }
}
