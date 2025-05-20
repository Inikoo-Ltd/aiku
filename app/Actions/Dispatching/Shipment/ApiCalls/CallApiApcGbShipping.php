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

class CallApiApcGbShipping extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function getAccessToken(Shipper $shipper): string
    {
        if (app()->environment('production')) {
            return Arr::get($shipper->settings, 'access_token');
        } else {
            return config('app.sandbox.shipper_apc_token');
        }
    }

    public function getBaseUrl(): string
    {
        if (app()->environment('production')) {
            return 'https://apc.hypaship.com';
        } else {
            return 'https://apc-training.hypaship.com';
        }
    }

    public function getHeaders(Shipper $shipper): array
    {
        return [
            'remote-user' => 'Basic '.$this->getAccessToken($shipper),
            'Content-Type' => 'application/json',
        ];
    }

    public function handle(DeliveryNote|PalletReturn $parent, Shipper $shipper): array
    {
        $url     = '/api/3.0/Orders.json';


        $parentResource = ShippingParentResource::make($parent)->getArray();

        $parcels = $parent->parcels;

        // postalcode
        $postalCode = '';


        $address2 = Arr::get($parentResource, 'to_address.address_line_2');

        if (in_array(
            Arr::get($parentResource, 'to_address.country_code'),
            [
                'GB',
                'IM',
                'JE',
                'GG'
            ]
        )) {
            $postalCode = Arr::get($parentResource, 'to_address.postal_code');
        } else {
            $postalCode = 'INT';
            $address2   = trim($address2.' '.trim(Arr::get($parentResource, 'to_address.sorting_code').' '.Arr::get($parentResource, 'to_address.postal_code')));
        }
        // end postalcode

        $items = [];
        foreach ($parcels as $parcel) {
            array_push(
                $items,
                [
                    'Type'   => 'ALL',
                    'Weight' => $parcel['weight'], // apc weight in kg
                    'Length' => $parcels[0]['dimensions'][0] ?? 0, // cm
                    'Width'  => $parcels[0]['dimensions'][1] ?? 0, // cm
                    'Height' => $parcels[0]['dimensions'][2] ?? 0 // cm
                ]
            );

        }

        $pickupDate = Carbon::createFromFormat('H:i', '17:30');

        $readyAt  = Carbon::createFromFormat('H:i', '16:00');
        $closedAt = Carbon::createFromFormat('H:i', '16:30');


        if ($pickupDate->gt($closedAt)) {
            $pickupDate = $pickupDate->addDay();
        }

        $prepareParams = [
            'CollectionDate'  => $pickupDate->format('d/m/Y'),
            'ReadyAt'         => $readyAt->format('H:i'),
            'ClosedAt'        => $closedAt->format('H:i'),
            'Reference'       => Str::limit($parent->reference, 30),
            'Delivery'        => [
                'CompanyName'  => Str::limit(Arr::get($parentResource, 'to_company_name'), 30),
                'AddressLine1' => Str::limit(Arr::get($parentResource, 'to_address.address_line_1'), 60),
                'AddressLine2' => Str::limit(Arr::get($parentResource, 'to_address.address_line_2'), 60),
                'PostalCode'   => Arr::get($parentResource, 'to_address.postal_code'),
                'City'         => Str::limit(Arr::get($parentResource, 'to_address.locality'), 31, ''),
                'County'       => Str::limit(Arr::get($parentResource, 'to_address.administrative_area'), 31, ''),
                'CountryCode'  => Arr::get($parentResource, 'to_address.country.code'),
                'Contact'      => [
                    'PersonName'  => Str::limit(Arr::get($parentResource, 'to_contact_name'), 60),
                    'PhoneNumber' => Str::limit(Arr::get($parentResource, 'to_phone'), 15, ''),
                    'Email'       => Arr::get($parentResource, 'to_email'),
                ],
                'Instructions' => Str::limit(preg_replace("/[^A-Za-z0-9 \-]/", '', strip_tags($parent?->customer_notes), 60)),

            ],
            'ShipmentDetails' => [
                'NumberOfPieces' => count($parcels),
                'Items'          => ['Item' => $items]
            ]
        ];

        // product code
        $productCode = '';
        if (count($parcels) == 1) {
            $dimensions = [
                $parcels[0]['dimensions'][1] ?? 0, // Width
                $parcels[0]['dimensions'][2] ?? 0,  // Height
                $parcels[0]['dimensions'][0] ?? 0, // Length
            ];
            rsort($dimensions);
            $weight = isset($parcels[0]['weight']) ? $parcels[0]['weight'] : 0;
            if ($weight <= 5 && $dimensions[0] <= 45 && $dimensions[1] <= 35 && $dimensions[2] <= 20) {
                $productCode = 'LW16';
            }
        }

        if (!preg_match('/^(BT51|IV(\d\s|20|25|30|31|32|33|34|35|36|37|63)|AB(41|51|52)|PA79)/', $postalCode)) {
            if (preg_match('/^((JE|GG|IM|KW|HS|ZE|IV)\d+)|AB(30|33|34|35|36|37|38)|AB[4-5][0-9]|DD[89]|FK(16)|PA(20|36|4\d|6\d|7\d)|PH((15|16|17|18|19)|[2-5][0-9])|KA(27|28)/', $postalCode)) {
                $productCode = 'TDAY';
            }
        }

        if ($productCode == '') {
            $productCode = 'ND16';
        }


        $prepareParams['ProductCode'] = $productCode;


        if (preg_match('/^BT/', $postalCode)) {
            $components = preg_split('/\s/', $postalCode);
            $postalCode = 'RD1';
            if (count($components) == 2) {
                $number = preg_replace('/[^0-9]/', '', $components[0]);
                if ($number > 17) {
                    $postalCode = 'RD2';
                }
            }
            $prepareParams['Delivery']['PostalCode'] = $postalCode;
            $prepareParams['ProductCode']            = 'ROAD';
        }
        // end product code

        $params = [
            'Orders' => [
                'Order' => $prepareParams
            ]
        ];



        $response = Http::withHeaders($this->getHeaders($shipper))->post($this->getBaseUrl().$url, $params);
        $apiResponse = $response->json();
        $statusCode = $response->status();

        $modelData = [
            'api_response' => $apiResponse,
        ];
        $errorData = [];

        if ($statusCode == 200 && Arr::get($apiResponse, 'Orders.Messages.Code') == 'SUCCESS') {
            $status                          = 'success';
            $orderNumber = Arr::get($apiResponse, 'Orders.Order.OrderNumber');
            $items = Arr::get($apiResponse, 'Orders.Order.ShipmentDetails.Items.Item');
            $modelData['pdf_label'] = $this->getLabel($orderNumber, $shipper);
            $modelData['number_parcels'] = (int) Arr::get($apiResponse, 'Orders.Order.ShipmentDetails.NumberOfPieces');



            $modelData['tracking']                  = Arr::get($apiResponse, 'Orders.Order.WayBill');
        } else {
            $status = 'fail';

            $errFields = Arr::get($apiResponse, 'Orders.Order.Messages.ErrorFields.ErrorField');

            if ($errFields) {
                if (!isset($errFields[0])) {
                    $errFields = [$errFields];
                }
                foreach ($errFields as $error) {
                    if ($error['FieldName'] == 'Delivery PostalCode') {
                        $errorData['others'][] = 'Invalid postcode';
                    } else {
                        $fieldParts = explode(' ', $error['FieldName']);

                        if (count($fieldParts) > 1) {
                            if (Str::contains($fieldParts[0], 'Delivery')) {
                                $errorData['address'][] = Str::headline($fieldParts[1]).' '.$error['ErrorMessage'];
                                continue;
                            }
                            $errorData[strtolower($fieldParts[0])][] = Str::headline($fieldParts[1]).' '.$error['ErrorMessage'];
                            continue;
                        }

                        $errorData['others'][] = Str::headline($error['FieldName']).' '.$error['ErrorMessage'];
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


    public function getLabel(string $labelID, Shipper $shipper): string
    {

        $apiResponse = Http::withHeaders($this->getHeaders($shipper))->get($this->getBaseUrl().'/api/3.0/Orders/'.$labelID.'.json')->json();
        return Arr::get($apiResponse, 'Orders.Order.Label.Content', '');
    }


    public string $commandSignature = 'sss';

    public function asCommand($command)
    {
        $f = PalletReturn::find(1016);
        $s = Shipper::find(19);
        $this->handle($f, $s);
    }
}
