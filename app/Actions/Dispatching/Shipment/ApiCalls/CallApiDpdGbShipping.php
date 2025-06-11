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
use App\Enums\Dispatching\Shipment\ShipmentLabelTypeEnum;
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

    private string $geoSession = '';
    private int $geoSessionDate = 0;
    private string $accountNumber = '';
    public function getBaseUrl(): string
    {
        if (app()->environment('production')) {
            return 'https://api.dpd.co.uk/';
        } else {
            return 'https://api.dpd.co.uk/';
        }
    }

    public function getAccessToken(Shipper $shipper): array
    {
        if (app()->environment('production')) {
            return Arr::get($shipper->settings, 'access_token');
        } else {
            return json_decode(config('app.sandbox.shipper_dpd_gb_token'), true);
        }
    }


    public function getHeaders(Shipper $shipper, string $accept = 'application/json'): array
    {
        if (empty($this->geoSession) ||
            Carbon::now()->timestamp - $this->geoSessionDate > 43200) {
            $this->login($shipper);
        }

        return [
            "GeoSession" => $this->geoSession,
            "Accept" => $accept,
            "Content-Type" => "application/json",
            "GeoClient" => "account/" . $this->accountNumber,
        ];
    }


    protected function login(Shipper $shipper): void
    {
        [$username, $password, $accountNumber] = array_values($this->getAccessToken($shipper));
        $headers = [
            "Authorization" => "Basic " . base64_encode($username . ':' . $password),
            "Content-Type" => "application/json",
            "Accept" => "application/json",
            "GeoClient" => "account/" . $accountNumber
        ];

        try {
            $response = Http::withHeaders($headers)->post($this->getBaseUrl() . 'user?action=login');
        } catch (\Exception $e) {
            return;
        }

        $apiResponse = $response->json();

        $this->geoSession = Arr::get($apiResponse, 'data.geoSession', '');
        $this->geoSessionDate = Carbon::now()->timestamp;
        $this->accountNumber = $accountNumber;

        return;
    }


    public string $commandSignature = 'dpd_gb';

    public function asCommand($command)
    {
        $p = PalletReturn::find(1046);
        $shipper = Shipper::find(21);
        $this->handle($p, $shipper);
    }

    public function getServices(Shipper $shipper, array $parcels, array $parentResource): array
    {
        $url = 'shipping/network/?';

        $totalWeight = 0;
        foreach ($parcels as $parcel) {
            $totalWeight += $parcel['weight'];
        }

        $data = [
            'businessUnit'                        => 0,
            'deliveryDirection'                   => 1, // 1 for outbound shipments, 2 for inbound shipments
            'numberOfParcels'                     => count($parcels),
            'shipmentType'                        => 0,
            'totalWeight'                         => $totalWeight,
            'deliveryDetails.address.countryCode' => Arr::get($parentResource, 'from_address.country.code', 'Unknown'),
            'deliveryDetails.address.postcode'    => Arr::get($parentResource, 'from_address.postal_code', 'Unknown'),
            'deliveryDetails.address.street'      => Arr::get($parentResource, 'from_address.address_line_1', 'Unknown'),
            'deliveryDetails.address.town'        => Arr::get($parentResource, 'from_address.locality', 'Unknown'),
            'deliveryDetails.address.county'      => Arr::get($parentResource, 'from_address.administrative_area', 'Unknown'),

            'collectionDetails.address.countryCode' => Arr::get($parentResource, 'to_address.country.code', 'Unknown'),
            'collectionDetails.address.postcode'    => Arr::get($parentResource, 'to_address.postal_code', 'Unknown'),
            'collectionDetails.address.street'      => Arr::get($parentResource, 'to_address.address_line_1', 'Unknown'),
            'collectionDetails.address.town'        => Arr::get($parentResource, 'to_address.locality', 'Unknown'),
            'collectionDetails.address.county'      => Arr::get($parentResource, 'to_address.administrative_area', 'Unknown'),
        ];

        $params = '';
        foreach ($data as $key => $value) {
            $params .= $key.'='.urlencode($value).'&';
        }
        $params = trim($params, '&');

        $response = Http::withHeaders($this->getHeaders($shipper))
            ->get($this->getBaseUrl() . $url, $params);

        return $response->json();
    }


    public function handle(DeliveryNote|PalletReturn $parent, Shipper $shipper): array
    {
        $url = 'shipping/shipment';
        $parentResource = ShippingParentResource::make($parent)->getArray();
        $parcels = $parent->parcels;

        data_set($parentResource, 'reference', $parent->reference);
        data_set($parentResource, 'customer_notes', $parent->customer_notes ?? '');

        $params = $this->prepareShipmentParams($parentResource, $parcels);

        $response = Http::withHeaders($this->getHeaders($shipper))
            ->post($this->getBaseUrl() . $url, $params);

        $apiResponse = $response->json();
        $statusCode = $response->status();

        $modelData = [
            'api_response' => $apiResponse,
        ];
        $errorData = [];

        if ($statusCode == 200 && Arr::get($apiResponse, 'error') == null) {
            $status = 'success';
            $trackingNumber = Arr::get($apiResponse, 'data.consignmentDetail.0.consignmentNumber');
            $shipmentId = Arr::get($apiResponse, 'data.shipmentId');

            $modelData['tracking'] = $trackingNumber;
            $modelData['shipment_id'] = $shipmentId;
            $modelData['label'] = $this->getLabel($shipmentId, $shipper, 'text/html');
            $modelData['label_type'] = ShipmentLabelTypeEnum::HTML;
            $modelData['number_parcels'] = count($parcels);
        } else {
            $status = 'fail';
            $errors = Arr::get($apiResponse, 'error', []);

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->processError($error, $errorData);
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
                            'contactName' => Arr::get($parentResource, 'from_contact_name', 'Unknown'),
                            'telephone' => preg_replace('/\s+/', '', Arr::get($parentResource, 'from_phone')),
                        ],
                        'address' => [
                            'organisation' => Arr::get($parentResource, 'from_company_name', 'Unknown'),
                            'countryCode' => Arr::get($parentResource, 'from_address.country_code', 'Unknown'),
                            'street' => Arr::get($parentResource, 'from_address.address_line_1', 'Unknown'),
                            'town' => Arr::get($parentResource, 'from_address.locality', 'Unknown'),
                            'county' => Arr::get($parentResource, 'from_address.administrative_area', 'Unknown'),
                            'postcode' => Arr::get($parentResource, 'from_address.postal_code', '')
                        ],
                    ],
                    'deliveryDetails' => [
                        'contactDetails' => [
                            'contactName' => Arr::get($parentResource, 'to_contact_name'),
                            'telephone' => preg_replace('/\s+/', '', Arr::get($parentResource, 'to_phone')),
                        ],
                        'address' =>  [
                            'organisation' => Arr::get($parentResource, 'to_company_name'),
                            'countryCode' => Arr::get($parentResource, 'to_address.country_code'),
                            'street' => Arr::get($parentResource, 'to_address.address_line_1'),
                            'town' => Arr::get($parentResource, 'to_address.locality'),
                            'county' => Arr::get($parentResource, 'to_address.administrative_area'),
                            'postcode' => Arr::get($parentResource, 'to_address.postal_code', '')
                        ],
                        'notificationDetails' => [
                            'email' => Arr::get($parentResource, 'to_email'),
                            'mobile' => preg_replace('/\s+/', '', Arr::get($parentResource, 'to_phone')),
                        ]
                    ],
                    'networkCode' => '1^12', // Default network code, could be configurable
                    'numberOfParcels' => count($parcels),
                    'totalWeight' => $totalWeight,
                    'shippingRef1' => Arr::get($parentResource, 'reference', ''),
                    'shippingRef2' => null,
                    'shippingRef3' => null,
                    'customsValue' => null,
                    'deliveryInstructions' => Arr::get($parentResource, 'customer_notes', 'test_development_aiku'),
                    'parcelDescription' => 'test_development_aiku',
                    'liabilityValue' => null,
                    'liability' => false
                ]
            ],
        ];
    }

    public function getLabel(string $shipmentId, Shipper $shipper, string $output): string
    {

        $response = Http::withHeaders($this->getHeaders($shipper, $output))
            ->get($this->getBaseUrl() . 'shipping/shipment/' . $shipmentId . '/label');

        if ($response->successful()) {
            return base64_encode($response->body());
        }

        return '';
    }
}
