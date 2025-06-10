<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 16-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dispatching\Shipment\ApiCalls;

use App\Actions\OrgAction;
use App\Http\Resources\Dispatching\ShippingParentResource;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipper;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use SoapClient;
use SoapFault;

class CallApiGlsSKShipping extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function getAccessToken(Shipper $shipper): array
    {
        if (app()->environment('production')) {
            return Arr::get($shipper->settings, 'access_token');
        } else {
            return json_decode(config('app.sandbox.shipper_gls_sk_token'), true);
        }
    }

    public function getBaseUrl(): string
    {
        return 'https://api.mygls.sk';
    }

    public function getHeaders(Shipper $shipper): array
    {
        return [
            'remote-user'  => 'Basic '.$this->getAccessToken($shipper),
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(DeliveryNote|PalletReturn $parent, Shipper $shipper): array
    {
        [$username, $password, $clientNumber] = array_values($this->getAccessToken($shipper));
        $url = $this->getBaseUrl() . '/ParcelService.svc?singleWsdl';

        $parentResource = ShippingParentResource::make($parent)->getArray();
        // $parcels        = $parent->parcels;

        // if (in_array(
        //     Arr::get($parentResource, 'to_address.country_code'),
        //     [
        //         'GB',
        //         'IM',
        //         'JE',
        //         'GG'
        //     ]
        // )) {
        //     $postalCode = Arr::get($parentResource, 'to_address.postal_code');
        // } else {
        //     $postalCode = 'INT';
        // }

        // $items = [];
        // foreach ($parcels as $parcel) {
        //     array_push(
        //         $items,
        //         [
        //             'Type'   => 'ALL',
        //             'Weight' => $parcel['weight'], // apc weight in kg
        //             'Length' => $parcels[0]['dimensions'][0] ?? 0, // cm
        //             'Width'  => $parcels[0]['dimensions'][1] ?? 0, // cm
        //             'Height' => $parcels[0]['dimensions'][2] ?? 0 // cm
        //         ]
        //     );
        // }


        // $serviceList = [];
        // if (!empty($parent->cash_on_delivery)) {
        //     $serviceList[] = (object)['Code' => 'COD'];
        // }

        $prepareParams = (object)[
            'ClientNumber' => $clientNumber,
            'ClientReference' => 'test-' . Str::limit($parent->reference, 30),
            'Content' => 'test',
            'Count' => 1,
            'DeliveryAddress' => (object)[
                'ContactEmail' => Arr::get($parentResource, 'to_email'),
                'ContactName' => Str::limit(Arr::get($parentResource, 'to_contact_name'), 60),
                'ContactPhone' => Str::limit(Arr::get($parentResource, 'to_phone'), 15, ''),
                'Name' => Str::limit(Arr::get($parentResource, 'to_company_name'), 30),
                'Street' => Str::limit(Arr::get($parentResource, 'to_address.address_line_1') . ' ' . Arr::get($parentResource, 'to_address.address_line_2'), 60),
                'City' => Str::limit(Arr::get($parentResource, 'to_address.locality'), 31, ''),
                'ZipCode' => Arr::get($parentResource, 'to_address.postal_code'),
                'CountryIsoCode' => Arr::get($parentResource, 'to_address.country.code')
            ],
            'PickupAddress' => (object)[
                'ContactName' => 'test' . Str::limit(Arr::get($parentResource, 'from_contact_name'), 60),
                'ContactPhone' => Str::limit(Arr::get($parentResource, 'from_phone'), 15, ''),
                'ContactEmail' => Arr::get($parentResource, 'from_email'),
                'Name' => Str::limit(Arr::get($parentResource, 'from_company_name'), 30),
                'Street' => Str::limit(Arr::get($parentResource, 'from_address.address_line_1') . ' ' . Arr::get($parentResource, 'from_address.address_line_2'), 60),
                'City' => Str::limit(Arr::get($parentResource, 'from_address.locality'), 31, ''),
                'ZipCode' => Arr::get($parentResource, 'from_address.postal_code'),
                'CountryIsoCode' => Arr::get($parentResource, 'from_address.country.code')
            ],
            'PickupDate' => Carbon::now()->format('Y-m-d')
        ];


        // Add COD amount if applicable
        // if (!empty($parent->cash_on_delivery)) {
        //     $prepareParams->CODAmount = (float)$parent->cash_on_delivery;
        //     $prepareParams->CODReference = Str::limit($parent->reference, 30);
        //     $prepareParams->ServiceList = $serviceList;
        // }
        // $parcels[] = $parcel;
        $printLabelsRequest = array(
            'Username'   => $username,
            'Password'   => hex2bin($password),
            'ParcelList' => array(
                $prepareParams
            ),
        );

        $printLabelsRequest = array("printLabelsRequest" => $printLabelsRequest);

        $soapOptions = array(
            'soap_version'   => SOAP_1_1,
            // 'stream_context' => stream_context_create(array('ssl' => array('cafile' => storage_path('app/cert/ca_cert.pem'))))
        );

        try {
            $client = new SoapClient($url, $soapOptions);
        } catch (SoapFault $e) {
            dd($e->getMessage());
            $result['errors'] = ['Soap API connection error'];

            return $result;
        }
        // dd($client);

        $apiResponse = $client->PrintLabels($printLabelsRequest)->PrintLabelsResult;

        $modelData = [
            'api_response' => $apiResponse,
        ];

        // dd($apiResponse);

        $errorData = [];

        // if ($apiResponse->Labels != "") {
        //     $status                      = 'success';
        //     $orderNumber                 = Arr::get($apiResponse, 'Orders.Order.OrderNumber');
        //     $modelData['pdf_label']      = ;
        //     $modelData['number_parcels'] = (int)Arr::get($apiResponse, 'Orders.Order.ShipmentDetails.NumberOfPieces');


        //     $modelData['tracking'] = Arr::get($apiResponse, 'Orders.Order.WayBill');
        // } else {
        //     $status = 'fail';

        //     $errFields = Arr::get($apiResponse, 'Orders.Order.Messages.ErrorFields.ErrorField');

        //     if ($errFields) {
        //         if (!isset($errFields[0])) {
        //             $errFields = [$errFields];
        //         }
        //         foreach ($errFields as $error) {
        //             if ($error['FieldName'] == 'Delivery PostalCode') {
        //                 $errorData['others'][] = 'Invalid postcode,';
        //             } else {
        //                 $fieldParts = explode(' ', $error['FieldName']);

        //                 if (count($fieldParts) > 1) {
        //                     if (Str::contains($fieldParts[0], 'Delivery')) {
        //                         $errorData['address'][] = Str::headline($fieldParts[1]).' '.$error['ErrorMessage'].',';
        //                         continue;
        //                     }
        //                     $errorData[strtolower($fieldParts[0])] .= Str::headline($fieldParts[1]).' '.$error['ErrorMessage'].',';
        //                     continue;
        //                 }

        //                 $errorData['others'][] = Str::headline($error['FieldName']).' '.$error['ErrorMessage'].',';
        //             }
        //         }

        //         foreach ($errorData as $key => $value) {
        //             $errorData[$key] = strtolower(rtrim(implode(' ', $value), ','));
        //         }
        //     }
        // }

        // return [
        //     'status'    => $status,
        //     'modelData' => $modelData,
        //     'errorData' => $errorData,
        // ];

        return [];
    }


    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function getLabel(string $labelID, Shipper $shipper): string
    {
        $apiResponse = Http::withHeaders($this->getHeaders($shipper))->get($this->getBaseUrl().'/api/3.0/Orders/'.$labelID.'.json')->json();

        return Arr::get($apiResponse, 'Orders.Order.Label.Content', '');
    }

    public string $commandSignature = 'xxx2';

    public function asCommand($command)
    {
        $d = PalletReturn::find(825);
        $s = Shipper::find(36);

        dd($this->handle($d, $s));

    }



}
