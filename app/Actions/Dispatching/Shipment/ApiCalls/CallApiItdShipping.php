<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment\ApiCalls;

use App\Actions\OrgAction;
use App\Http\Resources\Dispatching\ShippingParentResource;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipper;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CallApiItdShipping extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function getAccessToken(Shipper $shipper): string
    {
        if (app()->environment('production')) {
            return Arr::get($shipper->settings, 'access_token');
        } else {
            return config('app.sandbox.shipper_itd_token');
        }
    }

    public function getBaseUrl(): string
    {
        if (app()->environment('production')) {
            return 'https://api.connexx.co.uk/';
        } else {
            return 'https://nest-staging-53oj.onrender.com/';
        }
    }

    public function handle(DeliveryNote|PalletReturn $parent, Shipper $shipper): array
    {
        $url     = 'api/v1/shipments';
        $headers = [
            [
                'Content-Type' => 'application/json'
            ]
        ];


        $parcels = $parent->parcels;
        $packages = [
            [
                'packageType'   => 3,
                'packageLength' => $parcels[0]['dimensions'][0] ?? 0,
                'packageWidth'  => $parcels[0]['dimensions'][1] ?? 0,
                'packageHeight' => $parcels[0]['dimensions'][2] ?? 0,
                'packageWeight' => isset($parcels[0]['weight']) ? $parcels[0]['weight'] * 1000 : 0,
            ]
        ];


        if (app()->environment('production')) {
            $serviceId = 'YODEL_XPECT_MINI_48_M_S_POD_137954';
            // for premium fast shipping use 'YODEL_XPECT_MINI_48_M_S_POD_137954';
        } else {
            $serviceId = 'YODEL_XPECT_48_P_136240';
        }


        $parentResource = ShippingParentResource::make($parent)->getArray();

        $params = [
            'labelSize'       => '4x6',
            'doNotUseWebhook' => true,
            'shipments'       => [
                [
                    'serviceId' => $serviceId,

                    'orderNumber'            => $parent->reference,
                    'customerReference'      => Arr::get($parentResource, 'customer_reference'),
                    'reasonForExport'        => 'Gift',
                    'fromAddressFirstName'   => Arr::get($parentResource, 'from_first_name'),
                    'fromAddressLastName'    => Arr::get($parentResource, 'from_last_name'),
                    'fromAddressCompany'     => Arr::get($parentResource, 'from_company_name'),
                    'fromAddressPhone'       => Arr::get($parentResource, 'from_phone'),
                    'fromAddressEmail'       => Arr::get($parentResource, 'from_email'),
                    'fromAddressStreet1'     => Arr::get($parentResource, 'from_address.address_line_1'),
                    'fromAddressStreet2'     => Arr::get($parentResource, 'from_address.address_line_2'),
                    'fromAddressCity'        => Arr::get($parentResource, 'from_address.locality'),
                    'fromAddressCountyState' => Arr::get($parentResource, 'from_address.administrative_area'),
                    'fromAddressZip'         => Arr::get($parentResource, 'from_address.postal_code'),
                    'fromAddressCountryIso'  => Arr::get($parentResource, 'from_address.country.code'),
                    'toAddressFirstName'     => Arr::get($parentResource, 'from_first_name'),
                    'toAddressLastName'      => Arr::get($parentResource, 'to_last_name'),
                    'toAddressCompany'       => Arr::get($parentResource, 'to_company_name'),
                    'toAddressPhone'         => Arr::get($parentResource, 'to_phone'),
                    'toAddressEmail'         => Arr::get($parentResource, 'to_email'),
                    'toAddressStreet1'       => Arr::get($parentResource, 'to_address.address_line_1'),
                    'toAddressStreet2'       => Arr::get($parentResource, 'to_address.address_line_2'),
                    'toAddressCity'          => Arr::get($parentResource, 'to_address.locality'),
                    'toAddressCountyState'   => Arr::get($parentResource, 'to_address.administrative_area'),
                    'toAddressZip'           => Arr::get($parentResource, 'to_address.postal_code'),
                    'toAddressCountryIso'    => Arr::get($parentResource, 'to_address.country.code'),
                    'packages'               => $packages
                ]
            ]
        ];

        $apiResponse = Http::withHeaders($headers)->withToken($this->getAccessToken($shipper))->post($this->getBaseUrl().$url, $params)->json();

        $modelData = [
            'api_response' => $apiResponse,
        ];

        $errorData = [];
        if (Arr::get($apiResponse, 'data.status') == 'COMPLETE') {
            $status                          = 'success';
            $modelData['combined_label_url'] = $apiResponse['data']['combinedPdfUrl'];

            $modelData['trackings']     = [];
            $modelData['tracking_urls'] = [];
            $modelData['label_urls']    = [];

            foreach (Arr::get($apiResponse, 'data.shipments') as $shipment) {
                $modelData['reference'] = Arr::get($shipment, 'id');


                foreach (Arr::get($shipment, 'packages', []) as $package) {
                    $modelData['trackings'][]     = Arr::get($package, 'trackingCode');
                    $modelData['tracking_urls'][] = Arr::get($package, 'trackingUrl');
                    $modelData['label_urls'][]    = Arr::get($package, 'labelUrl');
                }
            }

            $modelData['tracking']                  = implode(' ', $modelData['trackings']);
            $modelData['number_shipment_trackings'] = count($modelData['trackings']);
            $modelData['number_parcels']            = count($modelData['label_urls']);
        } else {
            $status = 'fail';


            $errors = Arr::get($apiResponse, 'errors.data.0');
            $consignmentErrors = Arr::get($errors, 'errors.consignment', []);
            foreach ($consignmentErrors as $key => $errorArr) {
                $code = Arr::get($errorArr, '0.code');
                if ($code) {
                    $msg = $this->getNiceKey($key) . ' ' . Str::of($code)->replace('_', ' ')->lower();
                    if (Str::contains($key, 'Address')) {
                        $errorData['address'][] = $msg;
                    } elseif (Str::contains($key, 'customer')) {
                        $errorData['customer'][] = $msg;
                    } else {
                        $errorData['others'][] = $msg;
                    }
                }
            }

            $packageErrors = Arr::get($errors, 'errors.packages.0.errors', []);
            foreach ($packageErrors as $errorArr) {
                $code = Arr::get($errorArr, '0.code');
                if ($code) {
                    $errorData['others'][] = Str::of($code)->replace('_', ' ')->lower();
                }
            }
            dd($errorData);
        }

        return [
            'status'    => $status,
            'modelData' => $modelData,
            'errorData' => $errorData,
        ];
    }


    public function getNiceKey($key): string
    {
        $map = [
            'serviceId'              => 'service id',
            'orderNumber'            => 'order number',
            'customerReference'      => 'customer reference',
            'reasonForExport'        => 'reason for export',
            'fromAddressFirstName'   => 'address first name',
            'fromAddressLastName'    => 'address last name',
            'fromAddressCompany'     => 'address company',
            'fromAddressPhone'       => 'address phone',
            'fromAddressEmail'       => 'address email',
            'fromAddressStreet1'     => 'address street 1',
            'fromAddressStreet2'     => 'address street 2',
            'fromAddressCity'        => 'address city',
            'fromAddressCountyState' => 'address county state',
            'fromAddressZip'         => 'address zip',
            'fromAddressCountryIso'  => 'address country iso',
            'toAddressFirstName'     => 'address first name',
            'toAddressLastName'      => 'address last name',
            'toAddressCompany'       => 'address company',
            'toAddressPhone'         => 'address phone',
            'toAddressEmail'         => 'address email',
            'toAddressStreet1'       => 'address street 1',
            'toAddressStreet2'       => 'address street 2',
            'toAddressCity'          => 'address city',
            'toAddressCountyState'   => 'address county state',
            'toAddressZip'           => 'address zip',
            'toAddressCountryIso'    => 'address country iso',
            'packages'               => 'packages'
        ];
        return $map[$key] ?? $key;
    }
}
