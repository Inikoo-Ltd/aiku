<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment\Itd\Traits;

use App\Models\Dispatching\DeliveryNote;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Ordering\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

trait WithItdAuthorization
{
    public function getAccessToken(): string
    {
        return Arr::get($this->settings, 'access_token');
    }

    public function getBaseUrl(): string
    {
        return Arr::get($this->settings, 'base_url');
    }

    public function apiClient(array $headers = []): PendingRequest
    {
        return Http::withToken($this->getAccessToken())->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            ...$headers
        ])->baseUrl($this->getBaseUrl());
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function getServices()
    {
        $url = 'api/v1/carriers-services';
        $response = $this->apiClient()->get($url);

        return $response->json();
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function getTracking(array $trackingBarCodes = [])
    {
        $url = 'api/v1/tracking';
        $response = $this->apiClient()->post($url, [
            'trackingBarCodes' => $trackingBarCodes
        ]);

        return $response->json();
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function storeShipment(PalletReturn|Order|DeliveryNote $parent, array $shipmentData = [])
    {
        $url = 'api/v1/shipments';

        $packages = [
            [
                'packageType' => 3,
                'packageLength' => 30,
                'packageWidth' => 30,
                'packageHeight' => 30,
                'packageWeight' => 1000
            ]
        ];

        $response = $this->apiClient()->post($url, [
            'labelSize' => '4x6',
            'doNotUseWebhook' => true,
            'shipments' => [
                [
                    'serviceId' => Arr::get($shipmentData, 'service_id'),
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
        ]);

        return $response->json();
    }
}
