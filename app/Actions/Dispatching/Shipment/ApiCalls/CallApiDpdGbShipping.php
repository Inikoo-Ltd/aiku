<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 04-06-2025, Bali, Indonesia
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

class CallApiDpdGbShipping extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function getBaseUrl(): string
    {
        if (app()->environment('production')) {
            return 'https://api.dpd.co.uk/';
        } else {
            return 'https://api.dpd.co.uk/';
        }
    }

    public function getAccessToken(Shipper $shipper): string
    {
        if (app()->environment('production')) {
            return Arr::get($shipper->settings, 'access_token');
        } else {
            return config('app.sandbox.shipper_dpd_gb_token');
        }
    }


    public function getHeaders(Shipper $shipper, string $accept = 'application/json'): array
    {
        if (empty(Arr::get($shipper->settings, 'geo_session')) ||
            Carbon::now()->timestamp - Arr::get($shipper->settings, 'geo_session_date', 0) > 43200) {
            $this->login($shipper);
        }

        return [
            "GeoSession: " . Arr::get($shipper->settings, 'geo_session'),
            "Accept: " . $accept,
            "Content-Type: application/json",
            'GeoClient: account/' . Arr::get($shipper->credentials, 'account_number')
        ];
    }


    protected function login(Shipper $shipper): void
    {
        $accessToken = $this->getAccessToken($shipper);
        $headers = [
            "Authorization: Basic " . $accessToken,
            "Content-Type: application/json",
            "Accept: application/json",
            // 'GeoClient: account/' . Arr::get($shipper->credentials, 'account_number')
        ];

        $response = Http::withHeaders($headers)->post($this->getBaseUrl() . 'user?action=login');
        $apiResponse = $response->json();

        dd($response->status());

        // if ($response->successful() && !empty(Arr::get($apiResponse, 'data.geoSession'))) {
        //     $settings = $shipper->settings;
        //     $settings['geo_session'] = $apiResponse['data']['geoSession'];
        //     $settings['geo_session_date'] = Carbon::now()->timestamp;
        //     $shipper->settings = $settings;
        //     $shipper->save();
        // }
    }

    public string $commandSignature = 'login_dpd_gb';

    public function asCommand($command)
    {
        $shipper = Shipper::find(1);
        $this->login($shipper);
    }


    public function handle(DeliveryNote|PalletReturn $parent, Shipper $shipper): array
    {
        $url = 'shipping/shipment';

        $parentResource = ShippingParentResource::make($parent)->getArray();
        $parcels = $parent->parcels;

        $params = $this->prepareShipmentParams($parentResource, $parcels);

        $response = Http::withHeaders($this->getHeaders($shipper))
            ->post($this->getBaseUrl() . $url, $params);

        $apiResponse = $response->json();
        $statusCode = $response->status();

        $modelData = [
            'api_response' => $apiResponse,
        ];
        $errorData = [];

        if ($statusCode == 200 && empty($apiResponse['error'])) {
            $status = 'success';
            $trackingNumber = Arr::get($apiResponse, 'data.consignmentDetail.0.consignmentNumber');
            $shipmentId = Arr::get($apiResponse, 'data.shipmentId');

            $modelData['tracking'] = $trackingNumber;
            $modelData['shipment_id'] = $shipmentId;
            $modelData['pdf_label'] = $this->getLabel($shipmentId, $shipper);
            $modelData['number_parcels'] = count($parcels);
        } else {
            $status = 'fail';
            $errors = Arr::get($apiResponse, 'error', []);

            if (!empty($errors)) {
                if (!isset($errors[0]) && is_array($errors)) {
                    $this->processError($errors, $errorData);
                } else {
                    foreach ($errors as $error) {
                        $this->processError($error, $errorData);
                    }
                }
            }
        }

        return [
            'status' => $status,
            'modelData' => $modelData,
            'errorData' => $errorData,
        ];
    }


    protected function processError(array $error, array &$errorData): void
    {
        $obj = Arr::get($error, 'obj', '');
        $errorMessage = Arr::get($error, 'errorMessage', 'Unknown error');

        if (Str::contains($obj, 'consignment.networkCode')) {
            $errorData['service'] = 'Invalid service';
        } elseif (Str::contains($obj, 'address')) {
            $errorData['address'] = $errorMessage;
        } elseif (Str::contains($obj, 'contact')) {
            $errorData['contact'] = $errorMessage;
        } else {
            $errorData['others'][] = $errorMessage . ' (' . $obj . ')';
        }
    }

    protected function prepareShipmentParams(array $parentResource, array $parcels): array
    {
        $totalWeight = 0;
        foreach ($parcels as $parcel) {
            $totalWeight += $parcel['weight'];
        }

        $totalWeight = max($totalWeight, 0.1);

        $address = [
            'organisation' => Str::limit(Arr::get($parentResource, 'to_company_name'), 30),
            'countryCode' => Arr::get($parentResource, 'to_address.country.code'),
            'street' => Arr::get($parentResource, 'to_address.address_line_1'),
            'town' => Arr::get($parentResource, 'to_address.locality'),
            'county' => Arr::get($parentResource, 'to_address.administrative_area'),
            'postcode' => Arr::get($parentResource, 'to_address.postal_code', '')
        ];

        $address = array_filter($address);

        $now = Carbon::now();
        $collectionDate = $now->format('Y-m-d') . 'T' . $now->format('H:i') . ':00';

        return [
            'jobId' => null,
            'collectionOnDelivery' => false,
            'invoice' => null,
            'collectionDate' => $collectionDate,
            'consolidate' => false,
            'consignment' => [
                [
                    'consignmentNumber' => null,
                    'consignmentRef' => null,
                    'parcels' => [],
                    'collectionDetails' => [
                        'contactDetails' => [
                            'contactName' => config('app.company_contact_name', 'Contact Name'),
                            'telephone' => config('app.company_phone', '123456789'),
                        ],
                        'address' => [
                            'organisation' => config('app.company_name', 'Company Name'),
                            'countryCode' => config('app.company_country_code', 'GB'),
                            'street' => config('app.company_address_line_1', 'Address Line 1'),
                            'town' => config('app.company_town', 'Town'),
                            'county' => config('app.company_county', 'County'),
                        ],
                    ],
                    'deliveryDetails' => [
                        'contactDetails' => [
                            'contactName' => Arr::get($parentResource, 'to_contact_name'),
                            'telephone' => Arr::get($parentResource, 'to_phone'),
                        ],
                        'address' => $address,
                        'notificationDetails' => [
                            'email' => Arr::get($parentResource, 'to_email'),
                            'mobile' => Arr::get($parentResource, 'to_phone'),
                        ]
                    ],
                    'networkCode' => 'NDCC', // Default network code, could be configurable
                    'numberOfParcels' => count($parcels),
                    'totalWeight' => $totalWeight,
                    'shippingRef1' => $parentResource['reference'],
                    'shippingRef2' => null,
                    'shippingRef3' => null,
                    'customsValue' => null,
                    'deliveryInstructions' => Arr::get($parentResource, 'customer_notes', ''),
                    'parcelDescription' => '',
                    'liabilityValue' => null,
                    'liability' => false
                ]
            ],
        ];
    }

    public function getLabel(string $shipmentId, Shipper $shipper, string $output = 'pdf'): string
    {
        $accept = match ($output) {
            'html' => 'text/html',
            'clp' => 'text/vnd.citizen-clp',
            'epl' => 'text/vnd.eltron-epl',
            default => 'application/pdf',
        };

        $response = Http::withHeaders($this->getHeaders($shipper, $accept))
            ->get($this->getBaseUrl() . 'shipping/shipment/' . $shipmentId . '/label');

        if ($response->successful()) {
            return $response->body();
        }

        return '';
    }
}
