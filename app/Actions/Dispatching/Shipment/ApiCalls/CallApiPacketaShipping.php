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
        $creds = $this->getAccessToken($shipper);
        $apiPassword = Arr::get($creds, 'api_password');
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
        $addressId = $this->getAddressIdByCountryCode($countryCode, $weight);
        if (is_null($addressId)) {
            return [
                'status' => 'fail',
                'errorData' => [
                    'message' => "No address ID found for country code {$countryCode} and weight {$weight} kg.",
                ],
                'modelData' => [],
            ];
        }
        $value = $this->getInsuranceValueByCountryCode($countryCode, $weight, !empty($parent->cash_on_delivery));
        $packetAttributes = [
            'number' => Str::limit($parent->reference, 30),
            'name' => Arr::get($parentResource, 'to_first_name'),
            'surname' => Arr::get($parentResource, 'to_last_name'),
            'company' => Arr::get($parentResource, 'to_company_name'),
            'email' => Arr::get($parentResource, 'to_email'),
            'phone' => Arr::get($parentResource, 'to_phone'),
            'addressId' => $addressId,
            'value' => $value,
            'currency' => $order->currency?->code ?? 'EUR',
            'eshop' => 'AWGifts Europe',
            'weight' => $weight, // in kg
            'street' => Arr::get($parentResource, 'to_address.address_line_1'),
            'houseNumber' => Arr::get($parentResource, 'to_address.address_line_2'),
            'city' => Arr::get($parentResource, 'to_address.locality'),
            'zip' => Arr::get($parentResource, 'to_address.postal_code'),
            'note' => $parent->shipping_notes,
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
            $modelData['trackings'] = [$id];
            $modelData['tracking_urls'] = [];

            $modelData['tracking'] = $apiResponse->id;
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
        $accessToken = $this->getAccessToken($shipper);
        $apiPassword = Arr::get($accessToken, 'api_password');
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

    public function roles(): array
    {
        return [
            'AT' => [
                ['id' => 6830, 'ranges' => [[0, 1], [1, 2], [2, 5]], 'prices' => [5.10, 5.30, 5.70]],
            ],
            'BG' => [
                [
                    'id' => 26066,
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
                    'id' => 10618,
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
                    'id' => 106,
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
                    'id' => 25980,
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
                    'id' => 5060,
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
                    'id' => 4309,
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
                    'id' => 17465,
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
                    'id' => 4159,
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
                    'id' => 3828,
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
                    'id' => 9990,
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
                    'id' => 9103,
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
                    'id' => 25981,
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
                    'id' => 18807,
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
                    'id' => 25982,
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
                    'id' => 18808,
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
                    'id' => 4162,
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
                    'id' => 4655,
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
                    'id' => 7397,
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
                    'id' => 131,
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
                    'id' => 19515,
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
                    'id' => 25004,
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
                    'id' => 4653,
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
                    'id' => 4827,
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
     * sources https://client.packeta.com/en/user-conversions
     * Returns the COD and max packet value limits per country.
     * Format: [ 'COUNTRY_CODE' => ['cod' => value, 'cod_currency' => 'CUR', 'max' => value, 'max_currency' => 'CUR'] ]
     */
    public function rolesLimitValueByTOS(): array
    {
        return [
            'BE' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'BG' => ['cod' => 1500,    'cod_currency' => 'BGN', 'max' => 1500,     'max_currency' => 'BGN'],
            'CZ' => ['cod' => 20000,   'cod_currency' => 'CZK', 'max' => 20000,    'max_currency' => 'CZK'],
            'DK' => ['cod' => 5200,    'cod_currency' => 'DKK', 'max' => 5200,     'max_currency' => 'DKK'],
            'EE' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'FI' => ['cod' => 500,     'cod_currency' => 'EUR', 'max' => 500,      'max_currency' => 'EUR'],
            'FR' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'HR' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'IE' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'IT' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'IL' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'QA' => ['cod' => 500,     'cod_currency' => 'USD', 'max' => 800,      'max_currency' => 'USD'],
            'CY' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'LT' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'LV' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'LU' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'HU' => ['cod' => 250000,  'cod_currency' => 'HUF', 'max' => 250000,   'max_currency' => 'HUF'],
            'DE' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'NL' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'OM' => ['cod' => 500,     'cod_currency' => 'USD', 'max' => 800,      'max_currency' => 'USD'],
            'PL' => ['cod' => 3000,    'cod_currency' => 'PLN', 'max' => 3000,     'max_currency' => 'PLN'],
            'PT' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'AT' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'RO' => ['cod' => 3500,    'cod_currency' => 'RON', 'max' => 3500,     'max_currency' => 'RON'],
            'RU' => ['cod' => 52000,   'cod_currency' => 'RUB', 'max' => 52000,    'max_currency' => 'RUB'],
            'GR' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'SA' => ['cod' => 500,     'cod_currency' => 'USD', 'max' => 800,      'max_currency' => 'USD'],
            'SK' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'SI' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'AE' => ['cod' => 700,     'cod_currency' => 'AED', 'max' => 700,      'max_currency' => 'AED'],
            'GB' => ['cod' => 630,     'cod_currency' => 'GBP', 'max' => 630,      'max_currency' => 'GBP'],
            'US' => ['cod' => 500,     'cod_currency' => 'USD', 'max' => 800,      'max_currency' => 'USD'],
            'ES' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'SE' => ['cod' => 7500,    'cod_currency' => 'SEK', 'max' => 7500,     'max_currency' => 'SEK'],
            'CH' => ['cod' => 800,     'cod_currency' => 'CHF', 'max' => 800,      'max_currency' => 'CHF'],
            'TR' => ['cod' => 700,     'cod_currency' => 'EUR', 'max' => 700,      'max_currency' => 'EUR'],
            'UA' => ['cod' => 20500,   'cod_currency' => 'UAH', 'max' => 20500,    'max_currency' => 'UAH'],
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

    public string $commandSignature = 'xxx222x';

    public function asCommand($command)
    {
        $d = DeliveryNote::find(981605);
        $s = Shipper::find(37);
        dd($this->handle($d, $s));
    }
}
