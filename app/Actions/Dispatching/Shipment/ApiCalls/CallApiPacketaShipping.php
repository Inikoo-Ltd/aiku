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
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\Shipment\ShipmentLabelTypeEnum;
use App\Http\Resources\Dispatching\ShippingDeliveryNoteResource;
use App\Http\Resources\Dispatching\ShippingDropshippingDeliveryNoteResource;
use App\Http\Resources\Dispatching\ShippingPalletReturnResource;
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
        // dd($this->getLabel($apiPassword, 3141295364));
        // $apiPassword = $this->getAccessToken($shipper);
        $url = $this->getBaseUrl() . '/api/soap.wsdl';

        if ($parent instanceof PalletReturn) {
            $parentResource = ShippingPalletReturnResource::make($parent)->getArray();
        } elseif ($parent->shop->type == ShopTypeEnum::DROPSHIPPING) {
            $parentResource = ShippingDropshippingDeliveryNoteResource::make($parent)->getArray();
        } else {
            $parentResource = ShippingDeliveryNoteResource::make($parent)->getArray();
        }

        $parcels = $parent->parcels;
        $weight = collect($parcels)->sum('weight') ?? 0;
        $order = $parent->orders->first();
        $countryCode = Arr::get($parentResource, 'to_address.country.code', 'CZ');

        $packetAttributes = [
            'number' => Str::limit($parent->reference, 30),
            'name' => Arr::get($parentResource, 'to_first_name'),
            'surname' => Arr::get($parentResource, 'to_last_name'),
            'company' => Arr::get($parentResource, 'to_company_name'),
            'email' => Arr::get($parentResource, 'to_email'),
            'phone' => Arr::get($parentResource, 'to_phone'),
            'addressId' => $this->getAddressIdByCountryCode($countryCode),
            'value' => '100',
            'currency' => $order->currency?->code ?? 'EUR',
            'eshop' => Arr::get($parentResource, 'from_company_name', 'Aiku Development'),
            // 'eshop' => '123 Handmade',
            // 'eshopId' => , // Default to 1 if not set
            'weight' => $weight, // in kg
            'street' => Arr::get($parentResource, 'to_address.address_line_1'),
            'houseNumber' => Arr::get($parentResource, 'to_address.address_line_2'),
            'city' => Arr::get($parentResource, 'to_address.locality'),
            'zip' => Arr::get($parentResource, 'to_address.postal_code'),
            'note' => $parent->shipping_notes ?? 'aiku_development',
        ];

        // Add COD (Cash on Delivery) if applicable
        if (!empty($parent->cash_on_delivery)) {
            $packetAttributes['cod'] = (float)$parent->cash_on_delivery;
        }

        $errorData = [];
        $modelData = [];
        try {
            $client = new SoapClient($url);
            $apiResponse = $client->createPacket($apiPassword, $packetAttributes);
            $apiResponseData = json_decode(json_encode($apiResponse), true);

            $modelData = [
                'api_response' => $apiResponseData,
            ];

            $status = 'success';
            $id = $apiResponse->id ?? '';
            $modelData['label']      = $this->getLabel($id, $shipper);
            $modelData['label_type'] = ShipmentLabelTypeEnum::PDF;
            $modelData['number_parcels'] = $parcels ? count($parcels) : 1;

            $modelData['trackings']     = [];
            $modelData['tracking_urls'] = [];
            // if (!empty($modelData['label']) && $modelData['label_type'] === ShipmentLabelTypeEnum::PDF) {
            //     $pdfData = base64_decode($modelData['label']);
            //     $fileName = 'packeta_labels/label_' . ($id ?: uniqid()) . '.pdf';
            //     \Illuminate\Support\Facades\Storage::disk('local')->put($fileName, $pdfData);
            //     $modelData['label_file_path'] = storage_path('app/' . $fileName);
            // }
        } catch (SoapFault $e) {
            $status = 'fail';

            if (isset($e->detail->PacketAttributesFault)) {
                $faults = $e->detail->PacketAttributesFault->attributes->fault;
                if (!is_array($faults)) {
                    $faults = [$faults];
                }

                foreach ($faults as $fault) {
                    if (in_array($fault->name, ['street', 'houseNumber', 'city', 'zip']) && !isset($errorData['address'])) {
                        $errorData['address'] = "Invalid address for fields: ";
                    } elseif (!isset($errorData['others'])) {
                        $errorData['others'] = 'Invalid field: ';
                    }
                    switch ($fault->name) {
                        case 'street':
                            $errorData['address'] .= "address,";
                            break;
                        case 'houseNumber':
                            $errorData['address'] .= "address line 2,";
                            break;
                        case 'city':
                            $errorData['address'] .= "city,";
                            break;
                        case 'zip':
                            $errorData['address'] .= "postal code,";
                            break;
                        default:
                            $errorData['others'] .= "{$fault->name},";
                            break;
                    }
                }
            } elseif (isset($e->detail->IncorrectApiPasswordFault)) {
                $errorData['others'] = 'Incorrect API password';
            } else {
                $errorData['others'] = 'Unknown error';
            }

            if (isset($errorData['address'])) {
                $errorData['address'] = rtrim($errorData['address'], ',');
            } elseif (isset($errorData['others'])) {
                $errorData['others'] = rtrim($errorData['others'], ',');
            }

            $errorData['message'] =  $errorData['address'] ?? $errorData['others'];
        }

        return [
            'status'    => $status,
            'modelData' => $modelData,
            'errorData' => $errorData,
        ];
    }

    public function getLabel(string $labelID, Shipper $shipper): string
    {
        if (empty($labelID)) {
            return 'Label ID is empty';
        }
        [$_, $apiPassword] = array_values($this->getAccessToken($shipper));
        $url = $this->getBaseUrl() . '/api/soap.wsdl';
        $format = 'A6 on A6';
        $offset = 0;
        try {
            $client = new SoapClient($url);
            $result = $client->packetLabelPdf($apiPassword, $labelID, $format, $offset);
            return base64_encode($result);
        } catch (SoapFault $e) {
            return 'Could not retrieve label: ' . $e->getMessage();
        }
    }

    public function getAddressIdByCountryCode(string $countryCode): int
    {
        $addressIds = [
            'SK' => 131, // Slovakia
            'SI' => 19515, // Slovenia (default to first, can be adjusted)
            'SI-1' => 19515, // Slovenia
            'SI-2' => 25004, // Slovenia
            'ES' => 4653, // Spain
            'LV' => 25981, // Latvia
            'LT' => 25982, // Lithuania
            'PL' => 4162, // Poland
            'PT' => 4655, // Portugal
            'RO' => 7397, // Romania
            'EE' => 25980, // Estonia
            'FI' => 5060, // Finland
            'HU' => 4159, // Hungary
            'IT' => 9103, // Italy
            'HR' => 10618, // Croatia
            'CZ' => 106, // Czech Republic
        ];

        // Normalize country code to uppercase
        $countryCode = strtoupper($countryCode);

        // Special handling for Slovenia if needed
        // if ($countryCode === 'SI') {
        //     // You can implement logic to choose between 19515 and 25004 if needed
        //     return $addressIds['SI'];
        // }

        return $addressIds[$countryCode] ?? 131; // Default to Czech Republic if not found
    }

    public string $commandSignature = 'xxx222x';

    public function asCommand($command)
    {
        $d = DeliveryNote::find(981605);
        $s = Shipper::find(37);
        dd($this->handle($d, $s));
    }
}
