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

    public function handle(AllegroUser $allegroUser, array $data): void
    {
        $shippingZones = [
            'SK' => [[1, '2.70'], [2, '2.80'], [5, '2.90'], [15, '3.00'], [30, '3.50']],            // Zone 1
            'CZ' => [[1, '3.90'], [2, '4.30'], [5, '4.85'], [15, '5.85'], [30, '6.20']],            // Zone 2
            'PL' => [[1, '4.40'], [2, '4.80'], [5, '5.00'], [10, '6.00'], [15, '7.00'], [30, '7.50']], // Zone 4
            'HU' => [[1, '4.50'], [2, '4.70'], [5, '4.80'], [10, '5.60'], [30, '6.20']],            // Zone 5
        ];

        $dispatchCountry = $allegroUser->customerSalesChannel->customer?->address?->country_code ?? 'SK';

        $weightKg = (float) Arr::get($data, 'weight', 1.0);

        $resolveAmount = function (array $brackets, float $weight): ?string {
            foreach ($brackets as [$upTo, $amount]) {
                if ($weight <= $upTo) {
                    return $amount;
                }
            }
            return null;
        };

        try {
            $deliveryMethods = collect(Arr::get($allegroUser->getDeliveryMethods(), 'deliveryMethods'));

            $rates = [];
            foreach ($shippingZones as $country => $brackets) {
                $deliveryMethod = $deliveryMethods->firstWhere('destinationCountry', $country);
                if (! $deliveryMethod) {
                    continue;
                }

                $amount = $resolveAmount($brackets, $weightKg);
                if ($amount === null) {
                    continue;
                }

                $firstCurrency = Arr::get($deliveryMethod, 'shippingRatesConstraints.firstItemRate.currency');
                $nextCurrency  = Arr::get($deliveryMethod, 'shippingRatesConstraints.nextItemRate.currency');

                $rates[] = [
                    'deliveryMethod'        => ['id' => Arr::get($deliveryMethod, 'id')],
                    'maxQuantityPerPackage' => 1,
                    'firstItemRate'         => ['currency' => $firstCurrency, 'amount' => $amount],
                    'nextItemRate'          => ['currency' => $nextCurrency, 'amount' => '0.00'],
                ];
            }

            $shipping = ! empty($rates)
                ? $allegroUser->createShippingRates([
                    'name'            => 'AW-EU-'.$allegroUser->customerSalesChannel->slug.'-'.number_format($weightKg, 3, '.', ''),
                    'type'            => 'PHYSICAL',
                    'dispatchCountry' => $dispatchCountry,
                    'rates'           => $rates,
                ])
                : [];
        } catch (\Exception $e) {
            $shipping = [];
        }

        data_set($data, 'shipping_id', Arr::get($shipping, 'id'));
    }
}
