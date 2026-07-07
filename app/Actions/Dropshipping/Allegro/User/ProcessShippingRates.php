<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\User;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\AllegroUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ProcessShippingRates extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(AllegroUser $allegroUser): array
    {
        try {
            $shippingZones = [
                'SK' => [[1, '2.70'], [2, '2.80'], [5, '2.90'], [15, '3.00'], [30, '3.50']],
                'CZ' => [[1, '3.90'], [2, '4.30'], [5, '4.85'], [15, '5.85'], [30, '6.20']],
                'PL' => [[1, '4.40'], [2, '4.80'], [5, '5.00'], [10, '6.00'], [15, '7.00'], [30, '7.50']],
                'HU' => [[1, '4.50'], [2, '4.70'], [5, '4.80'], [10, '5.60'], [30, '6.20']]
            ];

            $countryCode = strtoupper((string)$allegroUser->customerSalesChannel->customer?->address?->country_code);
            $brackets = Arr::get($shippingZones, $countryCode, $shippingZones['SK']);
            $amount = $brackets[0][1] ?? null;

            $deliveryMethods = $allegroUser->getDeliveryMethods();
            $deliveryMethod = collect(Arr::get($deliveryMethods, 'deliveryMethods'))->firstWhere('destinationCountry', $countryCode);

            $maxWeightData = [];

            if(!$deliveryMethod) {
                return [];
            }

            if($amount === null) {
                return [];
            }

            if(Arr::get($deliveryMethod, 'shippingRatesConstraints.maxPackageWeight.supported')) {
                $maxWeightData = [
                    'maxPackageWeight' => [
                        'value' => 30,
                        'unit' => 'KILOGRAM'
                    ],
                ];
            }

            $shippingRatesData = [
                'name' => 'AW-EU-'.$allegroUser->customerSalesChannel->slug . '-' . $countryCode,
                'rates' => [
                    [
                        'deliveryMethod' => [
                            'id' => Arr::get($deliveryMethod, 'id'),
                        ],
                        'maxQuantityPerPackage' => 30,
                        ...$maxWeightData,
                        'firstItemRate' => [
                            'currency' => Arr::get($deliveryMethod, 'shippingRatesConstraints.firstItemRate.currency'),
                            'amount' => $amount
                        ],
                        'nextItemRate' => [
                            'currency' => Arr::get($deliveryMethod, 'shippingRatesConstraints.nextItemRate.currency'),
                            'amount' => '0'
                        ],
                    ]
                ]
            ];

            $shipping = $allegroUser->createShippingRates($shippingRatesData);
        } catch (\Exception $e) {
            $shipping = [];
        }

        return $shipping;
    }
}
