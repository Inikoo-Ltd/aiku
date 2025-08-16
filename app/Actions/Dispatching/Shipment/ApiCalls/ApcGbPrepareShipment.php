<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment\ApiCalls;

use App\Models\Helpers\Country;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ApcGbPrepareShipment
{
    use AsAction;
    use WithAttributes;
    public function handle($request, $pickUp, $shipTo, $parcelsData): array
    {
        try {
            $pickup_date = new Carbon(Arr::get($pickUp, 'date'));
        } catch (Exception $e) {
            echo $e->getMessage();
            $pickup_date = new Carbon();
        }


        if (Arr::get($shipTo, 'organization') != '') {
            $name = Arr::get($shipTo, 'organization');
        } else {
            $name = Arr::get($shipTo, 'contact');
        }

        if ($name == '') {
            $name = 'Householder';
        }

        $country = (new Country())->where('code', $shipTo['country_code'])->first();

        $address2 = Arr::get($shipTo, 'address_line_2');

        if (in_array(
            $country->code,
            [
                'GB',
                'IM',
                'JE',
                'GG'
            ]
        )) {
            $postalCode = Arr::get($shipTo, 'postal_code');
        } else {
            $postalCode = 'INT';
            $address2   = trim($address2.' '.trim(Arr::get($shipTo, 'sorting_code').' '.Arr::get($shipTo, 'postal_code')));
        }


        $items = [];
        foreach ($parcelsData as $parcelData) {
            $items[] = [
                'Type'   => 'ALL',
                'Weight' => $parcelData['weight'],
                'Length' => $parcelData['depth'],
                'Width'  => $parcelData['width'],
                'Height' => $parcelData['height']
            ];

        }

        $params = [
            'CollectionDate'  => $pickup_date->format('d/m/Y'),
            'ReadyAt'         => Arr::get($pickUp, 'ready', '16:30'),
            'ClosedAt'        => Arr::get($pickUp, 'end', '17:00'),
            'Reference'       => Str::limit($request->get('reference'), 30),
            'Delivery'        => [
                'CompanyName'  => Str::limit($name, 30),
                'AddressLine1' => Str::limit(Arr::get($shipTo, 'address_line_1'), 60),
                'AddressLine2' => Str::limit($address2, 60),
                'PostalCode'   => $postalCode,
                'City'         => Str::limit(Arr::get($shipTo, 'locality'), 31, ''),
                'County'       => Str::limit(Arr::get($shipTo, 'administrative_area'), 31, ''),
                'CountryCode'  => $country->code,
                'Contact'      => [
                    'PersonName'  => Str::limit(Arr::get($shipTo, 'contact'), 60),
                    'PhoneNumber' => Str::limit(Arr::get($shipTo, 'phone'), 15, ''),
                    'Email'       => Arr::get($shipTo, 'email'),
                ],
                'Instructions' => Str::limit(preg_replace("/[^A-Za-z0-9 \-]/", '', strip_tags($request->get('note'))), 60),


            ],
            'ShipmentDetails' => [
                'NumberOfPieces' => count($parcelsData),
                'Items'          => ['Item' => $items]
            ]
        ];

        if ($request->get('service_type') != '') {
            $params['ProductCode'] = $request->get('service_type');

            if ($params['ProductCode'] == 'MP16' || $params['ProductCode'] == 'CP16') {
                $params['ShipmentDetails']['NumberOfPieces'] = 1;

                $weight = $params['ShipmentDetails']['Items']['Item'][0]['Weight'];
                unset($params['ShipmentDetails']['Items']['Item']);
                $params['ShipmentDetails']['Items']['Item'][0]['Type']   = 'ALL';
                $params['ShipmentDetails']['Items']['Item'][0]['Weight'] = $weight;
            }


        } else {
            $productCode = '';
            if (count($parcelsData) == 1) {
                $dimensions = [
                    $parcelsData[0]['height'],
                    $parcelsData[0]['width'],
                    $parcelsData[0]['depth']
                ];
                rsort($dimensions);
                if ($parcelsData[0]['weight'] <= 5 && $dimensions[0] <= 45 && $dimensions[1] <= 35 && $dimensions[2] <= 20) {
                    $productCode = 'LW16';
                }

                if ($parcelsData[0]['weight'] <= 5 && $dimensions[0] <= 45 && $dimensions[1] <= 35 && $dimensions[2] <= 20) {
                    $productCode = 'LW16';
                }
            }


            if (!preg_match('/^(BT51|IV(\d\s|20|25|30|31|32|33|34|35|36|37|63)|AB(41|51|52)|PA79)/', $postalCode) && preg_match('/^((JE|GG|IM|KW|HS|ZE|IV)\d+)|AB(30|33|34|35|36|37|38)|AB[4-5][0-9]|DD[89]|FK(16)|PA(20|36|4\d|6\d|7\d)|PH((15|16|17|18|19)|[2-5][0-9])|KA(27|28)/', $postalCode)) {
                    $productCode = 'TDAY';
            }

            if ($productCode == '') {
                $productCode = 'ND16';
            }
            $params['ProductCode'] = $productCode;
        }


        if (str_starts_with($postalCode, 'BT')) {
            $components = preg_split('/\s/', $postalCode);
            $postalCode = 'RD1';
            if (count($components) == 2) {
                $number = preg_replace('/[^0-9]/', '', $components[0]);
                if ($number > 17) {
                    $postalCode = 'RD2';
                }
            }
            $params['Delivery']['PostalCode'] = $postalCode;
            $params['ProductCode']            = 'ROAD';
        }
        return $params;
    }
}
