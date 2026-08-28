<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 16-05-2025, Bali, Indonesia
 * GitHub: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dispatching\Shipment\ApiCalls;

use App\Actions\Dispatching\Shipment\GetShippingDeliveryNoteData;
use App\Actions\OrgAction;
use App\Enums\Dispatching\Shipment\ShipmentLabelTypeEnum;
use App\Http\Resources\Dispatching\ShippingPalletReturnResource;
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

class CallApiGlsSkShipping extends OrgAction
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
        return app()->isProduction() ? 'https://api.mygls.sk' : 'https://api.test.mygls.sk';
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(DeliveryNote|PalletReturn $parent, Shipper $shipper): array
    {
        $credentials  = $this->getAccessToken($shipper);
        $username     = Arr::get($credentials, 'username');
        $password     = Arr::get($credentials, 'password');
        $clientNumber = Arr::get($credentials, 'client_number');

        $url = $this->getBaseUrl().'/ParcelService.svc?singleWsdl';

        if ($parent instanceof PalletReturn) {
            $parentResource = ShippingPalletReturnResource::make($parent)->getArray();
        } else {
            $parentResource = GetShippingDeliveryNoteData::run($parent);
        }

        $parcels = $parent->parcels;

        data_set($parentResource, 'reference', $parent->reference);
        data_set($parentResource, 'shipping_notes', $parent->shipping_notes ?? '');

        $printLabelsRequest = array(
            'Username'   => $username,
            'Password'   => hex2bin($password),
            'ParcelList' => $this->prepareParcelList($parentResource, $clientNumber, $parcels),
        );

        $printLabelsRequest = array("printLabelsRequest" => $printLabelsRequest);

        $soapOptions = array(
            'soap_version' => SOAP_1_1,
        );

        try {
            $client      = new SoapClient($url, $soapOptions);
            $apiResponse = $client->PrintLabels($printLabelsRequest)->PrintLabelsResult;
        } catch (SoapFault $e) {
            $errorData = [
                'message' => 'Connection Error '.$e->getMessage(),
            ];

            return [
                'status'    => 'fail',
                'modelData' => [],
                'errorData' => $errorData,
            ];
        }

        $apiResponseData = json_decode(json_encode($apiResponse), true);
        if (Arr::get($apiResponseData, 'Labels')) {
            // Store the fact that labels exist, but not the actual binary content to save memory
            Arr::set($apiResponseData, 'Labels', 'Labels are present');
        }
        $modelData = [
            'api_response' => $apiResponseData,
        ];

        $errorData = [];

        if ($apiResponse->Labels ?? null) {
            $status                  = 'success';
            $modelData['label']      = base64_encode($apiResponse->Labels);
            $modelData['label_type'] = ShipmentLabelTypeEnum::PDF;

            $modelData['trackings']     = [];
            $modelData['tracking_urls'] = [];
            $trackingDatum              = $apiResponse->PrintLabelsInfoList->PrintLabelsInfo;
            if (is_array($trackingDatum)) {
                foreach ($trackingDatum as $trackingData) {
                    $modelData['trackings'][] = $trackingData->ParcelNumber;
                }
                $modelData['tracking'] = implode(' ', $modelData['trackings']);
            } else {
                $tracking_number          = $apiResponse->PrintLabelsInfoList->PrintLabelsInfo->ParcelNumber;
                $modelData['trackings'][] = $tracking_number;
                $modelData['tracking']    = $tracking_number;
            }


            $modelData['number_parcels'] = $parcels ? count($parcels) : 1;
        } else {
            $status = 'fail';

            $errFields = Arr::get($apiResponseData, 'PrintLabelsErrorList.ErrorInfo');

            if ($errFields) {
                if (!isset($errFields[0])) {
                    $errFields = [$errFields];
                }

                foreach ($errFields as $error) {
                    if (Str::contains($error['ErrorDescription'] ?? '', ['Pickup', 'Delivery'])) {
                        if (isset($errorData['address'])) {
                            continue;
                        }
                        $errorData['address'] = 'Invalid address';
                    } else {
                        $errorData['others'][] = $error['ErrorDescription'] ?? 'Unknown error';
                    }
                }

                $errorData['message'] = $errorData['address'] ?? $errorData['others'] ?? '';
            }
        }

        return [
            'status'    => $status,
            'modelData' => $modelData,
            'errorData' => $errorData,
        ];
    }

    /**
     * A cash on delivery amount is held per parcel. Count offers no way to vary it, so a
     *  multi-parcel COD shipment is sent as one entry per parcel with the whole amount on the
     * first and nothing on the rest. Everything else keeps using a single Count entry.
     *
     * @return array<int, object>
     */
    protected function prepareParcelList(array $parentResource, mixed $clientNumber, ?array $parcels): array
    {
        $numberParcels = $parcels ? count($parcels) : 1;
        $codAmount     = (float)Arr::get($parentResource, 'cash_on_delivery.amount');

        if ($codAmount <= 0 || $numberParcels === 1) {
            return [$this->prepareParcelParams($parentResource, $clientNumber, $parcels)];
        }

        $parcelList = [];
        foreach (range(1, $numberParcels) as $parcelNumber) {
            $parcel        = $this->prepareParcelParams($parentResource, $clientNumber, $parcels, $parcelNumber > 1);
            $parcel->Count = 1;

            if ($parcelNumber > 1) {
                $parcel->ClientReference = Str::limit(Arr::get($parentResource, 'reference').'-'.$parcelNumber, 30, '');
            }

            $parcelList[] = $parcel;
        }

        return $parcelList;
    }

    protected function prepareParcelParams(array $parentResource, mixed $clientNumber, ?array $parcels, bool $withoutCashOnDelivery = false): object
    {
        $shippingNotes = strip_tags(Arr::get($parentResource, 'shipping_notes') ?? '');
        $shippingNotes = Str::limit(preg_replace("/[^A-Za-z0-9 \-]/", '', $shippingNotes), 60, '');

        $contactName = Str::limit(Arr::get($parentResource, 'to_contact_name'), 60);
        if (!$contactName) {
            $contactName = Str::limit(Arr::get($parentResource, 'to_company_name'), 60);
        }
        if (!$contactName) {
            $contactName = 'anonymous';
        }

        $reference = Arr::get($parentResource, 'reference');

        $prepareParams = (object)[
            'ClientNumber'    => $clientNumber,
            'ClientReference' => Str::limit($reference, 30),
            'Content'         => app()->isProduction() ? $shippingNotes : 'test_development_aiku_'.$shippingNotes,
            'Count'           => $parcels ? count($parcels) : 1,
            'DeliveryAddress' => (object)[
                'ContactEmail'   => Arr::get($parentResource, 'to_email'),
                'ContactName'    => Arr::get($parentResource, 'to_contact_name'),
                'ContactPhone'   => Arr::get($parentResource, 'to_phone'),
                'Name'           => $contactName,
                'Street'         => Arr::get($parentResource, 'to_address.address_line_1').' '.Arr::get($parentResource, 'to_address.address_line_2'),
                'City'           => Arr::get($parentResource, 'to_address.locality'),
                'ZipCode'        => Arr::get($parentResource, 'to_address.postal_code'),
                'CountryIsoCode' => Arr::get($parentResource, 'to_address.country_code')
            ],
            'PickupAddress'   => (object)[
                'ContactName'    => Arr::get($parentResource, 'from_contact_name'),
                'ContactPhone'   => Arr::get($parentResource, 'from_phone'),
                'ContactEmail'   => Arr::get($parentResource, 'from_email'),
                'Name'           => Arr::get($parentResource, 'from_company_name'),
                'Street'         => Arr::get($parentResource, 'from_address.address_line_1').' '.Arr::get($parentResource, 'from_address.address_line_2'),
                'City'           => Arr::get($parentResource, 'from_address.locality'),
                'ZipCode'        => Arr::get($parentResource, 'from_address.postal_code'),
                'CountryIsoCode' => Arr::get($parentResource, 'from_address.country_code')
            ],
            'PickupDate'      => Carbon::now()->format('Y-m-d')
        ];

        $codAmount = (float)Arr::get($parentResource, 'cash_on_delivery.amount');
        if ($codAmount > 0 && !$withoutCashOnDelivery) {
            $prepareParams->CODAmount    = $codAmount;
            $prepareParams->CODCurrency  = Arr::get($parentResource, 'cash_on_delivery.currency');
            $prepareParams->CODReference = Str::limit($reference, 30);
        }

        return $prepareParams;
    }
}
