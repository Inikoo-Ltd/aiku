<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\User;

use App\Actions\Dropshipping\Allegro\Traits\WithAllegroDispatchLocation;
use App\Actions\Dropshipping\Allegro\Traits\WithAllegroShippingRates;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\AllegroUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class ProcessShippingRates extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithAllegroDispatchLocation;
    use WithAllegroShippingRates;

    private const int MAX_DELIVERY_METHOD_ATTEMPTS = 5;

    /**
     * Creates our shipping price list on the seller's account.
     *
     * The buyers are in the marketplace's country, not the seller's: a seller living in
     * Spain or Pakistan still sells to Poland on allegro-pl, so the zone and the delivery
     * method are picked by the marketplace.
     *
     * @return array{id?: string, message?: string}
     */
    public function handle(AllegroUser $allegroUser, ?string $marketplaceId = null): array
    {
        $shippingZones = [
            'SK' => [[1, '2.70'], [2, '2.80'], [5, '2.90'], [15, '3.00'], [30, '3.50']],
            'CZ' => [[1, '3.90'], [2, '4.30'], [5, '4.85'], [15, '5.85'], [30, '6.20']],
            'PL' => [[1, '4.40'], [2, '4.80'], [5, '5.00'], [10, '6.00'], [15, '7.00'], [30, '7.50']],
            'HU' => [[1, '4.50'], [2, '4.70'], [5, '4.80'], [10, '5.60'], [30, '6.20']]
        ];

        $marketplaceId = $marketplaceId ?? $allegroUser->marketplace_id ?? Arr::get($allegroUser->data, 'marketplace_id') ?? 'allegro-pl';
        $destinationCountry = strtoupper(Str::after($marketplaceId, 'allegro-'));
        $dispatchCountry = $this->allegroDispatchAddress()['country_code'];

        $brackets = Arr::get($shippingZones, $destinationCountry, $shippingZones['SK']);
        $amount = $brackets[0][1];

        try {
            $deliveryMethods = Arr::get($allegroUser->getDeliveryMethods(), 'deliveryMethods', []);
            $candidates = $this->allegroDeliveryMethodCandidates($deliveryMethods, $marketplaceId, $destinationCountry, $dispatchCountry);

            if ($candidates->isEmpty()) {
                return ['message' => "Allegro has no delivery method from $dispatchCountry to $destinationCountry on $marketplaceId."];
            }

            $result = [];
            foreach ($candidates->take(self::MAX_DELIVERY_METHOD_ATTEMPTS) as $deliveryMethod) {
                $result = $allegroUser->createShippingRates($this->allegroShippingRatesPayload($allegroUser, $deliveryMethod, $destinationCountry, $dispatchCountry, $amount));

                if (Arr::get($result, 'id')) {
                    return $result;
                }
            }

            return $result;
        } catch (\Exception $e) {
            Sentry::captureException($e);

            return ['message' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function allegroShippingRatesPayload(AllegroUser $allegroUser, array $deliveryMethod, string $destinationCountry, string $dispatchCountry, string $amount): array
    {
        $maxWeightData = [];
        if (Arr::get($deliveryMethod, 'shippingRatesConstraints.maxPackageWeight.supported')) {
            $maxWeightData = [
                'maxPackageWeight' => [
                    'value' => 30,
                    'unit' => 'KILOGRAM'
                ],
            ];
        }

        return [
            'name' => $this->allegroShippingRatesName($allegroUser, $destinationCountry),
            'type' => 'PHYSICAL',
            'dispatchCountry' => $dispatchCountry,
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
                        'currency' => Arr::get($deliveryMethod, 'shippingRatesConstraints.nextItemRate.currency')
                            ?? Arr::get($deliveryMethod, 'shippingRatesConstraints.firstItemRate.currency'),
                        'amount' => '0'
                    ],
                ]
            ]
        ];
    }

    /**
     * Prepaid methods Allegro lets us put on a price list, to the marketplace's country:
     * from our dispatch country first, then from anywhere.
     *
     * Allegro does not say which methods it keeps for company accounts (DPD Courier Poland
     * is one), so a private seller is refused the first pick and we try the next one.
     *
     * @return Collection<int, array>
     */
    public function allegroDeliveryMethodCandidates(array $deliveryMethods, string $marketplaceId, string $destinationCountry, string $dispatchCountry): Collection
    {
        $usable = collect($deliveryMethods)->filter(
            fn (array $method) => Arr::get($method, 'shippingRatesConstraints.allowed', true)
                && Arr::get($method, 'paymentPolicy') === 'IN_ADVANCE'
                && Arr::get($method, 'destinationCountry') === $destinationCountry
                && in_array($marketplaceId, Arr::get($method, 'marketplaces', [$marketplaceId]))
        );

        return $usable->where('dispatchCountry', $dispatchCountry)
            ->merge($usable->filter(fn (array $method) => Arr::get($method, 'dispatchCountry') === null))
            ->values();
    }
}
