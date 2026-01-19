<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 Jan 2026 12:58:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment\ApiCalls;

use App\Actions\Dispatching\Shipment\GetShippingDeliveryNoteData;
use App\Actions\OrgAction;
use App\Http\Resources\Dispatching\ShippingPalletReturnResource;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipper;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class CallApiDpdSkShipping extends OrgAction
{
    use AsAction;
    use WithAttributes;


    public function getBaseUrl(): string
    {
        return 'https://api.dpd.sk/shipment/json';
    }

    public function getAccessToken(Shipper $shipper): array
    {
        return [
            'ClientKey' => Arr::get($shipper->settings, 'apiKey'),
            'Email'     => Arr::get($shipper->settings, 'username'),
        ];
    }


    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(DeliveryNote|PalletReturn $parent, Shipper $shipper): array
    {
        if ($parent instanceof PalletReturn) {
            $parentResource = ShippingPalletReturnResource::make($parent)->getArray();
        } else {
            $parentResource = GetShippingDeliveryNoteData::run($parent);
        }

        $parcels = $parent->parcels;

        data_set($parentResource, 'reference', $parent->reference);
        data_set($parentResource, 'shipping_notes', $parent->shipping_notes ?? '');

        $params = $this->prepareShipmentParams($shipper, $parentResource, $parcels);


        $response = Http::withHeaders([
            "Content-Type" => "application/json"
        ])
            ->retry(3, 100)
            ->post($this->getBaseUrl(), $params);


        $apiResponse = $response->json();
        $statusCode  = $response->status();


        $modelData = [
            'api_response' => $apiResponse,
        ];
        $errorData = [];

        Sentry::captureMessage("answer to GSL SK " . json_encode($apiResponse));
        Sentry::captureMessage("status to GSL SK " . $statusCode);

        if ($statusCode == 200 && Arr::get($apiResponse, 'result.result.success')) {
            Sentry::captureMessage("A");
            $status = 'success';


            $trackingNumber = substr(Arr::get($apiResponse, 'result.result.mpsid'), 0, -8);


            $modelData['tracking']           = $trackingNumber;
            $modelData['combined_label_url'] = Arr::get($apiResponse, 'result.result.label');
            $modelData['trackings']          = [$trackingNumber];
            $modelData['label_urls']         = [Arr::get($apiResponse, 'result.result.label')];
            Sentry::captureMessage("A2");
        } else {
            Sentry::captureMessage("B");
            $status = 'fail';
            $error  = Arr::get($apiResponse, 'error', []);


            if (!empty($error)) {
                $errorMessage = Arr::get($error, 'message', 'Unknown error');

                if (Str::contains($errorMessage, 'Phone number')) {
                    $errorMessage = __('Invalid phone number');
                }
                $errorData['address'] = $errorMessage;
            } else {
                Sentry::captureMessage("Error 1: ". Arr::get($apiResponse, 'result','X1'));
                Sentry::captureMessage("Error 2: ". Arr::get($apiResponse, 'result.result','X2'));
                Sentry::captureMessage("Error 3: ". Arr::get($apiResponse, 'result.result.messages.0','Unknown error'));
                $errorData['address'] = Arr::get($apiResponse, 'result.result.messages.0','Unknown error');
            }
            Sentry::captureMessage("B2");
        }

        Sentry::captureMessage("status to GSL SK " . json_encode([
                'status'    => $status,
                'modelData' => $modelData,
                'errorData' => $errorData,
            ]));

        return [
            'status'    => $status,
            'modelData' => $modelData,
            'errorData' => $errorData,
        ];
    }


    protected function prepareShipmentParams(Shipper $shipper, array $parentResource, array $parcels): array
    {
        $parcelsData = [];
        foreach ($parcels as $parcel) {
            $depth  = (int)max(round(Arr::get($parcel, 'dimensions.0')), 1);
            $height = (int)max(round(Arr::get($parcel, 'dimensions.1')), 1);
            $width  = (int)max(round(Arr::get($parcel, 'dimensions.2')), 1);

            $weight = round($parcel['weight'], 1);
            if ($weight > 31.5) {
                $weight = '31.5';
            } elseif ($weight < 0.1) {
                $weight = '0.1';
            }

            $parcelsData[] = [
                'depth'  => $depth,
                'height' => $height,
                'width'  => $width,
                'weight' => $weight,
            ];
        }


        if (Arr::get($parentResource, 'to_company_name') != '') {
            $type       = 'b2b';
            $name       = Arr::get($parentResource, 'to_company_name');
            $nameDetail = Arr::get($parentResource, 'to_contact_name');
        } else {
            $type       = 'b2c';
            $name       = Arr::get($parentResource, 'to_contact_name');
            $nameDetail = '';
        }

        $postcode = trim(Arr::get($parentResource, 'to_address.sorting_code').' '.Arr::get($parentResource, 'to_address.postal_code'));


        if (!in_array(
            Arr::get($parentResource, 'to_address.country_code'),
            [
                'GB',
                'NL',
                'IE'
            ]
        )) {
            $postcode = preg_replace("/\D/", '', $postcode);
        }

        $phone = trim(Arr::get($parentResource, 'to_phone'));
        if (!preg_match('/^\+/', $phone) && $phone != '') {
            $phone = '+'.$phone;
        }

        $services = [];


        if (Arr::get($parentResource, 'cash_on_delivery')) {
            $orderID = preg_replace("/\D/", "", Arr::get($parentResource, 'reference'));
            if ($orderID == '') {
                $orderID = rand(1, 100);
            }

            $paymentMethod = 1; // 1: accept card, 0: accept cash only

            $services = [
                'cod' => [
                    'amount'         => Arr::get($parentResource, 'cash_on_delivery.amount'),
                    'currency'       => Arr::get($parentResource, 'cash_on_delivery.currency'),
                    'bankAccount'    => [
                        'id' => Arr::get($shipper->settings, 'bankID'),
                    ],
                    'variableSymbol' => $orderID,
                    'paymentMethod'  => $paymentMethod,
                ]
            ];
        }

        $note = Str::limit(strip_tags(Arr::get($parentResource, 'shipping_notes')), 35, '');
        if (!app()->environment('production')) {
            $note = 'Test do not dispatch '.$note;
            $note = Str::limit($note, 35, '');
        }
        $note = trim($note);
        if ($note == '') {
            $note = Arr::get($parentResource, 'reference');
        } else {
            $note = $note." (".Arr::get($parentResource, 'reference').")";
        }


        return [
            'jsonrpc' => '2.0',
            'method'  => 'create',
            'params'  => array(
                'DPDSecurity' => array(
                    'SecurityToken' => $this->getAccessToken($shipper),
                ),
                'shipment'    => [
                    'reference'        => Arr::get($parentResource, 'reference'),
                    'delisId'          => Arr::get($shipper->settings, 'delisId'),
                    'note'             => $note,
                    'product'          => 1,
                    'pickup'           => array(
                        'date'       => now()->format('Ymd'),
                        'timeWindow' => [
                            'end' => '1600'
                        ]
                    ),
                    'addressSender'    => array(
                        'id' => Arr::get($shipper->settings, 'pickupID'),
                    ),
                    'addressRecipient' => array(
                        'type'         => $type,
                        'name'         => Str::limit($name, 47),
                        'nameDetail'   => $nameDetail,
                        'street'       => Str::limit(Arr::get($parentResource, 'to_address.address_line_1'), 35, ''),
                        'streetDetail' => Str::limit(Arr::get($parentResource, 'to_address.address_line_2'), 35, ''),
                        'zip'          => $postcode,
                        'country'      => Arr::get($parentResource, 'to_address.country_iso_numeric'),
                        'city'         => Arr::get($parentResource, 'to_address.locality'),
                        'phone'        => $phone,
                        'email'        => Arr::get($parentResource, 'to_email'),

                    ),
                    'parcels'          => ['parcel' => $parcelsData],
                    'services'         => $services

                ],
            ),
            'id'      => 'null',
        ];
    }


}
