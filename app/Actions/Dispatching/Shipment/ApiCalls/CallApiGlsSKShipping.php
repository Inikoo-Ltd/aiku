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
use App\Enums\Dispatching\Shipment\ShipmentLabelTypeEnum;
use App\Http\Resources\Dispatching\ShippingParentResource;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipper;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
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

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(DeliveryNote|PalletReturn $parent, Shipper $shipper): array
    {
        [$username, $password, $clientNumber] = array_values($this->getAccessToken($shipper));
        $url = $this->getBaseUrl() . '/ParcelService.svc?singleWsdl';

        $parentResource = ShippingParentResource::make($parent)->getArray();
        $parcels        = $parent->parcels;

        $prepareParams = (object)[
            'ClientNumber' => $clientNumber,
            'ClientReference' => Str::limit($parent->reference, 30),
            'Content' => app()->isProduction() ? '' : 'test_development_aiku_' . ($parent->customer_notes ?? ''),
            'Count' => $parcels ? count($parcels) : 1,
            'DeliveryAddress' => (object)[
                'ContactEmail' => Arr::get($parentResource, 'to_email'),
                'ContactName' => Arr::get($parentResource, 'to_contact_name'),
                'ContactPhone' => Arr::get($parentResource, 'to_phone'),
                'Name' => Arr::get($parentResource, 'to_company_name'),
                'Street' => Arr::get($parentResource, 'to_address.address_line_1') . ' ' . Arr::get($parentResource, 'to_address.address_line_2'),
                'City' => Arr::get($parentResource, 'to_address.locality'),
                'ZipCode' => Arr::get($parentResource, 'to_address.postal_code'),
                'CountryIsoCode' => Arr::get($parentResource, 'to_address.country_code')
            ],
            'PickupAddress' => (object)[
                'ContactName' => Arr::get($parentResource, 'from_contact_name'),
                'ContactPhone' => Arr::get($parentResource, 'from_phone'),
                'ContactEmail' => Arr::get($parentResource, 'from_email'),
                'Name' => Arr::get($parentResource, 'from_company_name'),
                'Street' => Arr::get($parentResource, 'from_address.address_line_1') . ' ' . Arr::get($parentResource, 'from_address.address_line_2'),
                'City' => Arr::get($parentResource, 'from_address.locality'),
                'ZipCode' => Arr::get($parentResource, 'from_address.postal_code'),
                'CountryIsoCode' => Arr::get($parentResource, 'from_address.country_code')
            ],
            'PickupDate' => Carbon::now()->format('Y-m-d')
        ];

        // Add COD (Cash on Delivery) service if applicable
        // if (!empty($parent->cash_on_delivery)) {
        //     $prepareParams->ServiceList = [(object)['Code' => 'COD']];
        //     $prepareParams->CODAmount = (float)$parent->cash_on_delivery;
        //     $prepareParams->CODReference = Str::limit($parent->reference, 30);
        // }

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
        );

        try {
            $client = new SoapClient($url, $soapOptions);
        } catch (SoapFault $e) {
            $result['errorData'] = ['Soap API connection error'];
            $result['status'] = 'fail';
            $result['modelData'] = [
                'api_response' => [
                    'error' => $e->getMessage(),
                ],
            ];
            return $result;
        }

        $apiResponse = $client->PrintLabels($printLabelsRequest)->PrintLabelsResult;
        $apiResponseData = json_decode(json_encode($apiResponse), true);
        if (Arr::get($apiResponseData, 'Labels')) {
            // Store the fact that labels exist, but not the actual binary content to save memory
            Arr::set($apiResponseData, 'Labels', 'Labels are present');
        }
        $modelData = [
            'api_response' => $apiResponseData,
        ];

        $errorData = [];

        if ($apiResponse->Labels) {
            $status                      = 'success';
            $modelData['label']      = base64_encode($apiResponse->Labels);
            $modelData['label_type'] = ShipmentLabelTypeEnum::PDF;
            $tracking_number = $apiResponse->PrintLabelsInfoList->PrintLabelsInfo->ParcelNumber;
            $modelData['number_parcels'] = $parcels ? count($parcels) : 1;

            $modelData['tracking'] = $tracking_number;
        } else {
            $status = 'fail';

            $errFields = Arr::get($apiResponseData, 'PrintLabelsErrorList.ErrorInfo');
            if ($errFields) {

                if (!isset($errFields[0])) {
                    $errFields = [$errFields];
                }

                foreach ($errFields as $error) {
                    if (Str::contains($error['ErrorDescription'] ?? '', ['Pickup', 'Delivery'])) {
                        $errorData['address'][] = $error['ErrorDescription'] ?? 'Error in address';
                    } else {
                        $errorData['others'][] =  $error['ErrorDescription'] ?? 'Unknown error';
                    }
                }
            }
        }

        return [
            'status'    => $status,
            'modelData' => $modelData,
            'errorData' => $errorData,
        ];
    }

}
