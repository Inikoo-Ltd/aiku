<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 19-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dispatching\Shipment\ApiCalls;

use App\Actions\OrgAction;
use App\Enums\Dispatching\Shipment\ShipmentLabelTypeEnum;
use App\Http\Resources\Dispatching\ShippingParentResource;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipper;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use SoapClient;
use SoapFault;

class CallApiPacketaShipping extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function getAccessToken(Shipper $shipper): array
    {
        if (app()->environment('production')) {
            return Arr::get($shipper->settings, 'access_token');
        } else {
            return json_decode(config('app.sandbox.shipper_packeta_access_token'), true);
        }
    }

    public function getBaseUrl(): string
    {
        return 'https://www.zasilkovna.cz';
    }

    public function getPickupPointApiUrl(string $apiKey): string
    {
        return "https://pickup-point.api.packeta.com/v5/{$apiKey}/carrier/json?lang=en";
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(DeliveryNote|PalletReturn $parent, Shipper $shipper): array
    {
        [$_, $apiPassword] = array_values($this->getAccessToken($shipper));
        // $apiPassword = $this->getAccessToken($shipper);
        $url = $this->getBaseUrl() . '/api/soap.wsdl';

        $parentResource = ShippingParentResource::make($parent)->getArray();
        $parcels = $parent->parcels;
        // $packages = [
        //     [
        //         'packageType'   => 3,
        //         'packageLength' => $parcels[0]['dimensions'][0] ?? 0,
        //         'packageWidth'  => $parcels[0]['dimensions'][1] ?? 0,
        //         'packageHeight' => $parcels[0]['dimensions'][2] ?? 0,
        //         'packageWeight' => isset($parcels[0]['weight']) ? $parcels[0]['weight'] * 1000 : 0,
        //     ]
        // ];

        // Format Packeta specific packet attributes
        // Arr::get($parentResource, 'from_address.country.code', 'EUR')
        // dd($parent->currency);
        $packetAttributes = [
            'number' => Str::limit($parent->reference, 30),
            'name' => Arr::get($parentResource, 'to_first_name'),
            'surname' => Arr::get($parentResource, 'to_last_name'),
            'company' => Arr::get($parentResource, 'to_company_name'),
            'email' => Arr::get($parentResource, 'to_email'),
            'phone' => Arr::get($parentResource, 'to_phone'),
            'addressId' => 131, // carrier ID from settings
            'value' => "131",
            'currency' => 'EUR',
            'eshop' => Arr::get($parentResource, 'from_company_name', 'Unknown Eshop'),
            'weight' => $parcels[0]['weight'] ?? 0, // in kg
            'street' => Arr::get($parentResource, 'to_address.address_line_1', "test"),
            'houseNumber' => Arr::get($parentResource, 'to_address.address_line_2', "Test"),
            'city' => Arr::get($parentResource, 'to_address.locality', "test"),
            'zip' => Arr::get($parentResource, 'to_address.postal_code', "123"),
            'note' => $parent->customer_notes ?? 'aiku_development',
        ];
        // dd($packetAttributes);

        // Add COD (Cash on Delivery) if applicable
        if (!empty($parent->cash_on_delivery)) {
            $packetAttributes['cod'] = (float)$parent->cash_on_delivery;
        }

        try {
            $client = new SoapClient($url);
            $apiResponse = $client->createPacket($apiPassword, $packetAttributes);
            $apiResponseData = json_decode(json_encode($apiResponse), true);

            // dd("test->",$apiResponseData);

            $modelData = [
                'api_response' => $apiResponseData,
            ];

            $status = 'success';
            $errorData = [];

            // Store tracking information
            $modelData['tracking'] = $apiResponse->id ?? null;
            $modelData['label_type'] = ShipmentLabelTypeEnum::PDF;
            $modelData['number_parcels'] = $parcels ? count($parcels) : 1;

            // Get label if available
            if (isset($apiResponse->id)) {
                try {
                    $labelResponse = $client->packetLabelPdf($apiPassword, [$apiResponse->id]);
                    if (isset($labelResponse->labels)) {
                        $modelData['label'] = base64_encode($labelResponse->labels);
                    }
                } catch (SoapFault $e) {
                    $errorData['label'][] = 'Could not retrieve label: ' . $e->getMessage();
                }
            }

        } catch (SoapFault $e) {
            // dd($e);
            $status = 'fail';
            $modelData = [
                'api_response' => [
                    'error' => $e->getMessage(),
                ],
            ];
            $errorData = [];

            // $errorData = [];

            // if (isset($e->detail->PacketAttributesFault)) {
            //     $faults = $e->detail->PacketAttributesFault->attributes->fault;
            //     if (!is_array($faults)) {
            //         $faults = [$faults];
            //     }

            //     foreach ($faults as $fault) {
            //         if (in_array($fault->attribute, ['street', 'houseNumber', 'city', 'zip'])) {
            //             $errorData['address'][] = "Error in {$fault->attribute}: {$fault->fault}";
            //         } else {
            //             $errorData['others'][] = "{$fault->attribute}: {$fault->fault}";
            //         }
            //     }
            // } elseif (isset($e->detail->IncorrectApiPasswordFault)) {
            //     $errorData['authentication'][] = 'Incorrect API password';
            // } else {
            //     $errorData['others'][] = $e->getMessage();
            // }
        }

        return [
            'status'    => $status,
            'modelData' => $modelData,
            'errorData' => $errorData,
        ];
    }

    public string $commandSignature = 'xxx222';

    public function asCommand($command)
    {
        // $p = PalletReturn::find(1264);
        $d = DeliveryNote::find(976022);
        $s = Shipper::find(31);
        dd($this->handle($d, $s));
        // $f = Organisation::find(1);
        // $this->handle($f);
    }



}
