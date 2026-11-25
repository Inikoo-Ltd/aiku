<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 19-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dispatching\Shipment\ApiCalls;

use App\Actions\Dispatching\Shipment\GetShipmentDeliveryType;
use App\Actions\Dispatching\Shipment\GetShippingDeliveryNoteData;
use App\Actions\OrgAction;
use App\Actions\Ordering\Order\UpdateOrderPacketaPickupPoint;
use App\Enums\Dispatching\Shipment\ShipmentDeliveryTypeEnum;
use App\Enums\Dispatching\Shipment\ShipmentLabelTypeEnum;
use App\Http\Resources\Dispatching\ShippingPalletReturnResource;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipper;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Helpers\Address;
use App\Services\GeocoderService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Sentry;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use SoapClient;
use SoapFault;

class CallApiPacketaShipping extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public const int PL_INPOST_PACZKOMATY_BOX = 3060;

    public const int CY_BOX_NOW_BOX = 35369;

    public const int BOX_NOW_MAX_DISTANCE_KM = 25;

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
        return "https://pickup-point.api.packeta.com/v5/$apiKey/carrier/json?lang=en";
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(DeliveryNote|PalletReturn $parent, Shipper $shipper, ?string $senderName = null): array
    {
        $accessToken = $this->getAccessToken($shipper);
        $apiPassword = Arr::get($accessToken, 'api_password');
        $url         = $this->getBaseUrl().'/api/soap.wsdl';

        if ($parent instanceof PalletReturn) {
            $parentResource = ShippingPalletReturnResource::make($parent)->getArray();
        } else {
            $parentResource = GetShippingDeliveryNoteData::run($parent);
        }

        if (Arr::get($parentResource, 'cash_on_delivery')) {
            return [
                'status'    => 'fail',
                'errorData' => [
                    'message' => 'Packeta does not collect cash on delivery, use another shipper for this delivery note.',
                ],
                'modelData' => [],
            ];
        }

        $parcels     = $parent->parcels;
        $weight      = collect($parcels)->sum('weight') ?? 0;
        $order       = $parent->orders->first();
        $countryCode = Arr::get($parentResource, 'to_address.country_code', 'CZ');
        $isBoxNow    = strtoupper($countryCode) === 'CY';
        $lockerCode  = $isBoxNow ? null : $this->getParcelLockerCode($countryCode, $weight, [
            Arr::get($parentResource, 'to_company_name'),
            Arr::get($parentResource, 'to_address.address_line_1'),
            Arr::get($parentResource, 'to_address.address_line_2'),
        ], [
            $parent->shipping_notes,
            $parent->customer_notes,
        ]);
        $packetaPoint = null;
        if (!$isBoxNow && !$lockerCode) {
            $packetaPointSearch = FindPacketaPickupPoint::run($countryCode, [
                Arr::get($parentResource, 'to_company_name'),
                Arr::get($parentResource, 'to_address.address_line_1'),
                Arr::get($parentResource, 'to_address.address_line_2'),
                Arr::get($parentResource, 'to_address.locality'),
            ], [
                $parent->shipping_notes,
                $parent->customer_notes,
            ], data_get($order?->data, UpdateOrderPacketaPickupPoint::DATA_KEY));
            $packetaPoint = $packetaPointSearch['point'];
            if ($refusal = $this->refusePacketaPoint($packetaPointSearch, $weight, $countryCode)) {
                return [
                    'status'    => 'fail',
                    'errorData' => ['message' => $refusal],
                    'modelData' => [],
                ];
            }
        }
        if (!$isBoxNow && !$lockerCode && !$packetaPoint && $parent instanceof DeliveryNote && GetShipmentDeliveryType::run($parent)['delivery_type'] === ShipmentDeliveryTypeEnum::PICKUP_POINT) {
            return [
                'status'    => 'fail',
                'errorData' => [
                    'message' => 'This delivery goes to a pick-up point that Packeta cannot book automatically (Packeta points and Z-BOXes, and Polish InPost parcel lockers under 15 kg are supported). Book it manually with the pick-up point, or write the Packeta point number in the shipping notes (e.g. Z-BOX 31693).',
                ],
                'modelData' => [],
            ];
        }
        $addressId   = $lockerCode ? self::PL_INPOST_PACZKOMATY_BOX : ($packetaPoint ? (int) $packetaPoint['id'] : $this->getAddressIdByCountryCode($countryCode, $weight));
        if (is_null($addressId)) {
            return [
                'status'    => 'fail',
                'errorData' => [
                    'message' => "No address ID found for country code $countryCode and weight $weight kg.",
                ],
                'modelData' => [],
            ];
        }
        if ($isBoxNow) {
            if (!Arr::get($parentResource, 'to_phone')) {
                return [
                    'status'    => 'fail',
                    'errorData' => [
                        'message' => 'BoxNow sends the locker pick-up code to the recipient by SMS, add the recipient phone number.',
                    ],
                    'modelData' => [],
                ];
            }
            $lockerCode = $this->findBoxNowLockerCode($parent, Arr::get($accessToken, 'api_key'));
            if (!$lockerCode) {
                return [
                    'status'    => 'fail',
                    'errorData' => [
                        'message' => 'No BoxNow locker found within '.self::BOX_NOW_MAX_DISTANCE_KM.' km of this address. Write "BoxNow" and the locker code (e.g. BoxNow 6007) in the shipping notes, or use another shipper.',
                    ],
                    'modelData' => [],
                ];
            }
        }
        $value            = $this->getInsuranceValueByCountryCode($countryCode, $weight);
        $packetAttributes = [
            'number'      => Str::limit($parent->reference, 30),
            'name'        => Arr::get($parentResource, 'to_first_name'),
            'surname'     => Arr::get($parentResource, 'to_last_name'),
            'company'     => Arr::get($parentResource, 'to_company_name'),
            'email'       => Arr::get($parentResource, 'to_email') ?: Arr::get($parentResource, 'from_email'),
            'phone'       => Arr::get($parentResource, 'to_phone'),
            'addressId'   => $addressId,
            'value'       => $value,
            'currency'    => $order->currency?->code ?? 'EUR',
            'eshop'       => $senderName ?: Arr::get($parentResource, 'from_company_name'),
            'weight'      => $weight, // in kg
            'street'      => Arr::get($parentResource, 'to_address.address_line_1'),
            'houseNumber' => Arr::get($parentResource, 'to_address.address_line_2'),
            'city'        => Arr::get($parentResource, 'to_address.locality'),
            'zip'         => Arr::get($parentResource, 'to_address.postal_code'),
            'note'        => Str::limit((string) $parent->shipping_notes, 128, '') ?: null,
        ];
        if ($lockerCode) {
            $packetAttributes['carrierPickupPoint'] = $lockerCode;
        }
        if ($addressId === self::PL_INPOST_PACZKOMATY_BOX) {
            $dimensions                            = Arr::get($parcels, '0.dimensions', []);
            $packetAttributes['size']               = [
                'length' => (int) round(Arr::get($dimensions, 0, 0) * 10),
                'width'  => (int) round(Arr::get($dimensions, 1, 0) * 10),
                'height' => (int) round(Arr::get($dimensions, 2, 0) * 10),
            ];
        }

        $errorData = [];
        $modelData = [];
        try {
            $client          = $this->soapClient($url);
            $apiResponse     = $client->createPacket($apiPassword, $packetAttributes);
            $apiResponseData = json_decode(json_encode($apiResponse), true);

            $modelData                   = [
                'api_response' => $apiResponseData,
            ];
            $status                      = 'success';
            $id                          = $apiResponse->id ?? '';
            $modelData['label']          = $this->getLabel($id, $shipper);
            $modelData['label_type']     = ShipmentLabelTypeEnum::PDF;
            $modelData['number_parcels'] = $parcels ? count($parcels) : 1;
            $modelData['trackings']      = [$id];
            $modelData['tracking_urls']  = [];

            $modelData['tracking'] = $apiResponse->id;

            if ($lockerCode || $packetaPoint) {
                $modelData['delivery_type']     = ShipmentDeliveryTypeEnum::PICKUP_POINT;
                $modelData['pickup_point_code'] = $lockerCode ?: $packetaPoint['id'];
            }
        } catch (SoapFault $e) {
            $status = 'fail';

            if (isset($e->detail->PacketAttributesFault)) {
                $faults = $e->detail->PacketAttributesFault->attributes->fault;
                if (!is_array($faults)) {
                    $faults = [$faults];
                }

                foreach ($faults as $fault) {
                    if ($fault->name === 'addressId' && $packetaPoint) {
                        $errorData['others'] = "Packeta does not take parcels for pickup point {$packetaPoint['id']} {$packetaPoint['name']} now ($fault->fault). Write the number of another point in the shipping notes (e.g. Z-BOX 31693), or book it manually.";
                        continue;
                    }
                    if (in_array($fault->name, ['eshop', 'eshop_id'])) {
                        $errorData['sender'] = 'Sender "'.$packetAttributes['eshop'].'" was not found in Packeta or does not match the Indication of any sender in the Packeta client portal.';
                        continue;
                    }

                    if (in_array($fault->name, ['street', 'houseNumber', 'city', 'zip', 'phone']) && !isset($errorData['address'])) {
                        $errorData['address'] = "Invalid address for fields: ";
                    } elseif (!isset($errorData['others'])) {
                        $errorData['others'] = 'Invalid field: ';
                    }
                    switch ($fault->name) {
                        case 'phone':
                            $errorData['address'] .= "phone: ".$fault->fault."  ,";
                            break;
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
                            $errorData['others'] .= "$fault->name,";
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

            $errorData['message'] = $errorData['sender'] ?? $errorData['address'] ?? $errorData['others'];
        }

        return [
            'status'    => $status,
            'modelData' => $modelData,
            'errorData' => $errorData,
        ];
    }

    /**
     * @param  array{mentioned: bool, point: array<string, mixed>|null, candidates: array<int, array<string, mixed>>, unavailable: bool, asks_for_point: bool, removed_point?: array<string, mixed>|null}  $search
     */
    public function refusePacketaPoint(array $search, float $weight, string $countryCode): ?string
    {
        $point = $search['point'];
        if ($removedPoint = $search['removed_point'] ?? null) {
            return "The customer chose Packeta pickup point {$removedPoint['id']} {$removedPoint['name']}, which Packeta no longer lists. Write the number of another point in the shipping notes (e.g. Z-BOX 31693), or book it manually.";
        }
        if ($point && $point['country'] !== strtolower($countryCode)) {
            return "Packeta pickup point {$point['id']} {$point['name']} is not in the country of the delivery address ($countryCode). Ask the customer for a point in $countryCode.";
        }
        if ($point && $point['max_weight'] && $weight > $point['max_weight']) {
            return "The parcel weighs $weight kg and Packeta pickup point {$point['id']} {$point['name']} takes at most {$point['max_weight']} kg. Ask the customer for home delivery or another point.";
        }

        if (!$point && $search['candidates']) {
            return 'The notes fit more than one Packeta pickup point: '.collect($search['candidates'])->map(fn (array $candidate) => $candidate['id'].' '.$candidate['name'])->implode('; ').'. Write the number of the one the customer chose in the shipping notes (e.g. Z-BOX 31693).';
        }

        if (!$point && $search['unavailable'] && $search['asks_for_point']) {
            return 'The notes mention a Packeta pickup point but the list of Packeta points is not loaded yet, so it cannot be checked. Try again later or book it manually.';
        }

        if (!$point && $search['asks_for_point']) {
            return 'The notes ask for a Packeta pickup point or Z-BOX that aiku cannot find in the Packeta list. Write its number in the shipping notes (e.g. Z-BOX 31693), or book it manually.';
        }

        return null;
    }

    public function soapClient(string $url): SoapClient
    {
        return new SoapClient($url);
    }

    public function getLabel(string $labelID, Shipper $shipper): string
    {
        if (empty($labelID)) {
            return 'Label ID is empty';
        }
        $accessToken = $this->getAccessToken($shipper);
        $apiPassword = Arr::get($accessToken, 'api_password');

        try {
            $client = $this->soapClient($this->getBaseUrl().'/api/soap.wsdl');
            try {
                $courierNumber = $client->packetCourierNumber($apiPassword, $labelID);

                return base64_encode($client->packetCourierLabelPdf($apiPassword, $labelID, $courierNumber));
            } catch (SoapFault $e) {
                if (!isset($e->detail->NotSupportedFault)) {
                    throw $e;
                }
            }

            return base64_encode($client->packetLabelPdf($apiPassword, $labelID, 'A6 on A6', 0));
        } catch (SoapFault $e) {
            return 'Could not retrieve label: '.$e->getMessage();
        }
    }

    /**
     * @param  array<int, string|null>  $addressLines
     * @param  array<int, string|null>  $notes
     */
    public function getParcelLockerCode(string $countryCode, float $weight, array $addressLines, array $notes = []): ?string
    {
        if (strtoupper($countryCode) !== 'PL' || $weight >= 15) {
            return null;
        }

        $text = implode(' ', array_filter([...$addressLines, ...$notes]));
        if (preg_match('/paczkomat|inpost|parcel\s*locker/iu', $text)
            && preg_match('/(?<!-)\b([A-Z]{3,4}\d{2,3}[A-Z]{0,4})\b/', $text, $matches)) {
            return $matches[1];
        }

        foreach ($notes as $note) {
            $bareCode = strtoupper(trim((string) $note, " \t\n\r'\"."));
            if (preg_match('/^[A-Z]{3,4}\d{2,3}[A-Z]{1,4}$/', $bareCode)) {
                return $bareCode;
            }
        }

        return null;
    }

    /**
     * HELP-4845. Cyprus goes to BoxNow lockers only, and dropship orders come with a home address,
     * so the parcel goes to the open locker nearest the address. A locker written in the notes as
     * "BoxNow 6007" wins; a bare number is not read because Cyprus postcodes look the same.
     */
    public function findBoxNowLockerCode(DeliveryNote|PalletReturn $parent, ?string $apiKey): ?string
    {
        $points = $this->getBoxNowPoints((string) $apiKey);
        if (!$points) {
            return null;
        }

        $codes = array_column($points, 'code');
        if (preg_match('/box\s*now\D{0,10}(\d{4,5})\b/iu', implode(' ', array_filter([$parent->shipping_notes, $parent->customer_notes ?? null])), $matches)
            && in_array($matches[1], $codes, true)) {
            return $matches[1];
        }

        $address = $parent->deliveryAddress;
        if (!$address) {
            return null;
        }

        $coordinates = $this->locateAddress($address);

        if (!$coordinates) {
            return collect($points)->firstWhere('zip', trim((string) $address->postal_code))['code'] ?? null;
        }

        $nearest = collect($points)
            ->map(fn (array $point) => [
                'code'     => $point['code'],
                'distance' => $this->distanceInKm(
                    (float) $coordinates['latitude'],
                    (float) $coordinates['longitude'],
                    (float) Arr::get($point, 'coordinates.latitude'),
                    (float) Arr::get($point, 'coordinates.longitude')
                ),
            ])
            ->sortBy('distance')
            ->first();

        return $nearest['distance'] <= self::BOX_NOW_MAX_DISTANCE_KM ? $nearest['code'] : null;
    }

    /**
     * @return array<int, array{code: string, coordinates: array{latitude: string, longitude: string}, zip: string}>
     */
    public function getBoxNowPoints(string $apiKey): array
    {
        $cacheKey = 'packeta-box-now-points';
        $points   = Cache::get($cacheKey);
        if ($points) {
            return $points;
        }

        try {
            $points = collect(Http::timeout(30)->get($this->getBaseUrl()."/api/$apiKey/point.json", ['ids' => self::CY_BOX_NOW_BOX])->throw()->json('carriers.0.points', []))
                ->where('displayFrontend', 1)
                ->map(fn (array $point) => [...$point, 'code' => (string) $point['code']])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Sentry::captureException($e);

            return [];
        }

        if ($points) {
            Cache::put($cacheKey, $points, now()->addDay());
        }

        return $points;
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    /**
     * The geocoder falls back to the middle of the country when nothing else matches, which
     * would send the parcel to a locker near Nicosia whatever the address, so that is no match.
     * Each layer waits 1.5 s on Nominatim, hence the result is kept, misses included.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function locateAddress(Address $address): ?array
    {
        $cacheKey = 'packeta-box-now-geocode-'.md5(implode('|', [$address->address_line_1, $address->address_line_2, $address->locality, $address->postal_code, $address->country_code]));

        return Cache::remember($cacheKey, now()->addDay(), function () use ($address) {
            $result = $this->geocodeAddress($address);

            return ['coordinates' => $result && Arr::get($result, 'matched_layer') !== 'country_level' ? $result : null];
        })['coordinates'];
    }

    public function geocodeAddress(Address $address): ?array
    {
        return (new GeocoderService())->geocodeLayered([
            'address_line_1'      => $address->address_line_1,
            'address_line_2'      => $address->address_line_2,
            'dependent_locality'  => $address->dependent_locality,
            'locality'            => $address->locality,
            'administrative_area' => $address->administrative_area,
            'postal_code'         => $address->postal_code,
            'country_code'        => $address->country_code,
            'country_name'        => $address->country?->name,
        ]);
    }

    private function distanceInKm(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $latitudeDelta  = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);
        $haversine      = sin($latitudeDelta / 2) ** 2 + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($haversine), sqrt(1 - $haversine));
    }

    public function roles(): array
    {
        return [
            'CY' => [
                ['id' => self::CY_BOX_NOW_BOX, 'ranges' => [[0, 1], [1, 2], [2, 5], [5, 7.001]], 'prices' => [6.18, 6.22, 6.27, 6.37]],
            ],
            'AT' => [
                ['id' => 6830, 'ranges' => [[0, 1], [1, 2], [2, 5]], 'prices' => [5.10, 5.30, 5.70]],
            ],
            'BG' => [
                [
                    'id'     => 26066,
                    'ranges' => [
                        [0, 1],
                        [1, 2],
                        [2, 5],
                        [5, 10],
                        [10, 15],
                        [15, 30]
                    ],
                    'prices' => [
                        5.30,  // 0-1 kg
                        5.60,  // 1-2 kg
                        6.35,  // 2-5 kg
                        8.55,  // 5-10 kg
                        9.70,  // 10-15 kg
                        11.40  // 15-30 kg
                    ]
                ],
            ],
            'HR' => [
                [
                    'id'     => 10618,
                    'ranges' => [
                        [0, 1],
                        [1, 2],
                        [2, 5],
                        [5, 10],
                        [10, 30]
                    ],
                    'prices' => [
                        4.90,  // 0-1 kg
                        5.80,  // 1-2 kg
                        5.95,  // 2-5 kg
                        6.30,  // 5-10 kg
                        6.90   // 10-30 kg
                    ]
                ],
            ],
            'CZ' => [
                [
                    'id'     => 106,
                    'ranges' => [
                        [0, 1],
                        [1, 2],
                        [2, 5],
                        [5, 10]
                    ],
                    'prices' => [
                        3.90,  // 0-1 kg
                        4.30,  // 1-2 kg
                        4.85,  // 2-5 kg
                        5.85   // 5-10 kg
                    ]
                ],
            ],
            'EE' => [
                [
                    'id'     => 25980,
                    'ranges' => [
                        [0, 2],   // €8.60
                        [2, 5],   // €9.40
                    ],
                    'prices' => [
                        8.60,     // 0-2 kg
                        9.40,     // 2-5 kg
                    ]
                ],
                [
                    'id'     => 5060,
                    'ranges' => [
                        [5, 10],  // €11.90
                        [10, 15], // €12.70
                        [15, 30], // €14.30
                    ],
                    'prices' => [
                        11.90,    // 5-10 kg
                        12.70,    // 10-15 kg
                        14.30,    // 15-30 kg
                    ]
                ],
            ],
            'FR' => [
                [
                    'id'     => 4309,
                    'ranges' => [
                        [0, 0.25],  // 0-0.25 kg
                        [2, 5],     // 2-5 kg
                    ],
                    'prices' => [
                        7.50,   // 0-0.25 kg
                        12.90,  // 2-5 kg
                    ]
                ],
            ],
            'GR' => [
                [
                    'id'     => 17465,
                    'ranges' => [
                        [0, 1],    // €6.10
                        [1, 2],    // €6.80
                        [2, 5],    // €9.15
                        [5, 10],   // €13.30
                        [10, 15],  // €18.35
                        [15, 30],  // €22.99
                    ],
                    'prices' => [
                        6.10,   // 0-1 kg
                        6.80,   // 1-2 kg
                        9.15,   // 2-5 kg
                        13.30,  // 5-10 kg
                        18.35,  // 10-15 kg
                        22.99,  // 15-30 kg
                    ]
                ],
            ],
            'HU' => [
                [
                    'id'     => 4159,
                    'ranges' => [
                        [0, 1],   // 0-1 kg
                        [1, 2],   // 1-2 kg
                    ],
                    'prices' => [
                        4.50,     // 0-1 kg
                        4.70,     // 1-2 kg
                    ]
                ],
                [
                    'id'     => 3828,
                    'ranges' => [
                        [2, 5],   // 2-5 kg
                        [5, 10],  // 5-10 kg
                    ],
                    'prices' => [
                        4.80,     // 2-5 kg
                        5.60,     // 5-10 kg
                    ]
                ],
            ],
            'IE' => [
                [
                    'id'     => 9990,
                    'ranges' => [
                        [2, 5], // 2-5 kg
                    ],
                    'prices' => [
                        14.60, // €14.60 for 2-5 kg
                    ]
                ],
            ],
            'IT' => [
                [
                    'id'     => 9103,
                    'ranges' => [
                        [0, 2],   // 0-2 kg
                        [2, 5],   // 2-5 kg
                    ],
                    'prices' => [
                        7.80,    // 0-2 kg
                        9.20,    // 2-5 kg
                    ]
                ],
            ],
            'LV' => [
                [
                    'id'     => 25981,
                    'ranges' => [
                        [0, 2],   // 0-2 kg
                        [2, 5],   // 2-5 kg
                    ],
                    'prices' => [
                        6.30,    // 0-2 kg
                        7.50,    // 2-5 kg
                    ]
                ],
                [
                    'id'     => 18807,
                    'ranges' => [
                        [5, 10],   // 5-10 kg
                        [10, 15],  // 10-15 kg
                        [15, 30],  // 15-30 kg
                    ],
                    'prices' => [
                        8.20,    // 5-10 kg
                        8.90,    // 10-15 kg
                        12.00,   // 15-30 kg
                    ]
                ],
            ],
            'LT' => [
                [
                    'id'     => 25982,
                    'ranges' => [
                        [0, 2],   // 0-2 kg
                        [2, 5],   // 2-5 kg
                    ],
                    'prices' => [
                        6.30,    // 0-2 kg
                        6.80,    // 2-5 kg
                    ]
                ],
                [
                    'id'     => 18808,
                    'ranges' => [
                        [5, 10],   // 5-10 kg
                        [10, 30],  // 10-30 kg
                    ],
                    'prices' => [
                        8.40,    // 5-10 kg
                        9.50,    // 10-30 kg
                    ]
                ],
            ],
            'PL' => [
                [
                    'id'     => 4162,
                    'ranges' => [
                        [0, 1],    // 0-1 kg
                        [1, 2],    // 1-2 kg
                        [2, 5],    // 2-5 kg
                        [5, 10],   // 5-10 kg
                        [10, 15],  // 10-15 kg
                        [15, 30],  // 15-30 kg
                    ],
                    'prices' => [
                        4.40,   // 0-1 kg
                        4.80,   // 1-2 kg
                        5.00,   // 2-5 kg
                        6.00,   // 5-10 kg
                        7.00,   // 10-15 kg
                        7.50,   // 15-30 kg
                    ]
                ],
            ],
            'PT' => [
                [
                    'id'     => 4655,
                    'ranges' => [
                        [0, 2],    // 0-2 kg
                        [2, 5],    // 2-5 kg
                        [5, 10],   // 5-10 kg
                        [10, 15],  // 10-15 kg
                    ],
                    'prices' => [
                        7.80,   // 0-2 kg
                        8.20,   // 2-5 kg
                        10.90,  // 5-10 kg
                        11.70,  // 10-15 kg
                    ]
                ],
            ],
            'RO' => [
                [
                    'id'     => 7397,
                    'ranges' => [
                        [2, 5], // 2-5 kg
                    ],
                    'prices' => [
                        9.90, // €9.90 for 2-5 kg
                    ]
                ],
            ],
            'SK' => [
                [
                    'id'     => 131,
                    'ranges' => [
                        [0, 1],    // 0-1 kg
                        [1, 2],    // 1-2 kg
                        [2, 5],    // 2-5 kg
                        [5, 15],   // 5-15 kg
                        [15, 30],  // 15-30 kg
                    ],
                    'prices' => [
                        2.80,   // 0-1 kg
                        2.70,   // 1-2 kg
                        2.90,   // 2-5 kg
                        3.00,   // 5-15 kg
                        3.50,   // 15-30 kg
                    ]
                ],
            ],
            'SI' => [
                [
                    'id'     => 19515,
                    'ranges' => [
                        [0, 2],    // 0-2 kg
                        [2, 5],    // 2-5 kg
                        [15, 30],  // 15-30 kg
                    ],
                    'prices' => [
                        5.40,   // 0-2 kg
                        5.50,   // 2-5 kg
                        8.20,   // 15-30 kg
                    ]
                ],
                [
                    'id'     => 25004,
                    'ranges' => [
                        [5, 10],   // 5-10 kg
                        [10, 15],  // 10-15 kg
                    ],
                    'prices' => [
                        6.56,   // 5-10 kg
                        6.80,   // 10-15 kg
                    ]
                ],
            ],
            'ES' => [
                [
                    'id'     => 4653,
                    'ranges' => [
                        [0, 2],    // 0-2 kg
                        [2, 5],    // 2-5 kg
                        [5, 10],   // 5-10 kg
                        [10, 15],  // 10-15 kg
                    ],
                    'prices' => [
                        7.85,   // 0-2 kg
                        8.20,   // 2-5 kg
                        9.90,   // 5-10 kg
                        10.80,  // 10-15 kg
                    ]
                ],
            ],
            'SE' => [
                [
                    'id'     => 4827,
                    'ranges' => [
                        [2, 5],   // 2-5 kg
                        [5, 10],  // 5-10 kg
                    ],
                    'prices' => [
                        15.10,   // 2-5 kg
                        16.30,   // 5-10 kg
                    ]
                ],
            ],

        ];
    }

    /**
     * Sources https://client.packeta.com/en/user-conversions
     * Returns the COD and max packet value limits per country.
     * Format: [ 'COUNTRY_CODE' => ['cod' => value, 'cod_currency' => 'CUR', 'max' => value, 'max_currency' => 'CUR'] ]
     */
    public function rolesLimitValueByTOS(): array
    {
        return [
            'BE' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'BG' => ['cod' => 1500, 'cod_currency' => 'BGN', 'max' => 1500, 'max_currency' => 'BGN'],
            'CZ' => ['cod' => 20000, 'cod_currency' => 'CZK', 'max' => 20000, 'max_currency' => 'CZK'],
            'DK' => ['cod' => 5200, 'cod_currency' => 'DKK', 'max' => 5200, 'max_currency' => 'DKK'],
            'EE' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'FI' => ['cod' => 500, 'cod_currency' => 'EUR', 'max' => 500, 'max_currency' => 'EUR'],
            'FR' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'HR' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'IE' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'IT' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'IL' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'QA' => ['cod' => 500, 'cod_currency' => 'USD', 'max' => 800, 'max_currency' => 'USD'],
            'CY' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'LT' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'LV' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'LU' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'HU' => ['cod' => 250000, 'cod_currency' => 'HUF', 'max' => 250000, 'max_currency' => 'HUF'],
            'DE' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'NL' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'OM' => ['cod' => 500, 'cod_currency' => 'USD', 'max' => 800, 'max_currency' => 'USD'],
            'PL' => ['cod' => 3000, 'cod_currency' => 'PLN', 'max' => 3000, 'max_currency' => 'PLN'],
            'PT' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'AT' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'RO' => ['cod' => 3500, 'cod_currency' => 'RON', 'max' => 3500, 'max_currency' => 'RON'],
            'RU' => ['cod' => 52000, 'cod_currency' => 'RUB', 'max' => 52000, 'max_currency' => 'RUB'],
            'GR' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'SA' => ['cod' => 500, 'cod_currency' => 'USD', 'max' => 800, 'max_currency' => 'USD'],
            'SK' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'SI' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'AE' => ['cod' => 700, 'cod_currency' => 'AED', 'max' => 700, 'max_currency' => 'AED'],
            'GB' => ['cod' => 630, 'cod_currency' => 'GBP', 'max' => 630, 'max_currency' => 'GBP'],
            'US' => ['cod' => 500, 'cod_currency' => 'USD', 'max' => 800, 'max_currency' => 'USD'],
            'ES' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'SE' => ['cod' => 7500, 'cod_currency' => 'SEK', 'max' => 7500, 'max_currency' => 'SEK'],
            'CH' => ['cod' => 800, 'cod_currency' => 'CHF', 'max' => 800, 'max_currency' => 'CHF'],
            'TR' => ['cod' => 700, 'cod_currency' => 'EUR', 'max' => 700, 'max_currency' => 'EUR'],
            'UA' => ['cod' => 20500, 'cod_currency' => 'UAH', 'max' => 20500, 'max_currency' => 'UAH'],
        ];
    }

    /**
     * Determine the address ID based on country code and weight.
     * Returns null if the weight is not in the allowed range.
     */
    public function getAddressIdByCountryCode(string $countryCode, float $weight): ?int
    {
        $countryCode = strtoupper($countryCode);

        $roles = $this->roles();

        if (!isset($roles[$countryCode])) {
            return null;
        }

        foreach ($roles[$countryCode] as $rule) {
            foreach ($rule['ranges'] as [$min, $max]) {
                if ($weight >= $min && $weight < $max) {
                    return $rule['id'];
                }
            }
        }

        return null;
    }

    public function getInsuranceValueByCountryCode(string $countryCode, float $weight, bool $isCod = false): ?float
    {
        return 100;
    }


}
