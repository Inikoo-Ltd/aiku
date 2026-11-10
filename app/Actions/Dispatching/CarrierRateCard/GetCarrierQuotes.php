<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\CarrierRateCard;

use App\Models\Dispatching\CarrierRateCard;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Prices one parcel with every carrier service whose rate card is valid on the date.
 * The zone is the one whose postcode prefix matches longest; a zone with no prefixes covers the whole country.
 * A zone may set its own volumetric_kg_per_m3 and max_kg (islands by sea, small islands by air).
 * A weight between two bands is charged at the higher band; above the top band each extra kg (rounded up) is added.
 */
class GetCarrierQuotes
{
    use AsAction;

    /**
     * @param array{0: float, 1: float, 2: float}|null $dimensionsCm
     * @return array{quotes: array<int, array<string, mixed>>, not_offered: array<int, string>}
     */
    public function handle(Organisation $organisation, string $country, ?string $postcode, float $weightKg, ?array $dimensionsCm = null, ?string $date = null): array
    {
        $country  = strtoupper($country);
        $postcode = $postcode ? preg_replace('/[^0-9A-Z]/', '', strtoupper($postcode)) : '';
        $quotes   = [];
        $missing  = [];

        $cards = CarrierRateCard::where('organisation_id', $organisation->id)->validOn($date ?? now()->toDateString())->orderBy('carrier')->orderBy('service')->get();

        foreach ($cards as $card) {
            $label = "$card->carrier $card->service";
            $zone  = $this->zone($card, $country, $postcode);
            if (!$zone) {
                $missing[] = "$label: does not deliver there";
                continue;
            }
            $maxKg = (float) ($zone['max_kg'] ?? $card->max_kg);
            if ($weightKg > $maxKg) {
                $missing[] = "$label: over its $maxKg kg limit to {$zone['name']}";
                continue;
            }

            $volumetricKg = $dimensionsCm ? $dimensionsCm[0] * $dimensionsCm[1] * $dimensionsCm[2] / 1_000_000 * ($zone['volumetric_kg_per_m3'] ?? $card->volumetric_kg_per_m3) : 0;
            $chargeableKg = max($weightKg, $volumetricKg);
            $base         = $this->price($zone, $chargeableKg);
            if ($base === null) {
                $missing[] = "$label: no price for ".round($chargeableKg, 2)." kg in {$zone['name']}";
                continue;
            }

            $quotes[] = [
                'carrier'           => $card->carrier,
                'service'           => $card->service,
                'zone'              => $zone['name'],
                'chargeable_kg'     => round($chargeableKg, 2),
                'base_price'        => round($base, 2),
                'surcharge_percent' => (float) $card->surcharge_percent,
                'total'             => round($base * (1 + (float) $card->surcharge_percent / 100), 2),
                'valid_to'          => $card->valid_to?->toDateString(),
                'notes'             => $card->notes,
            ];
        }

        usort($quotes, fn (array $a, array $b) => $a['total'] <=> $b['total']);

        return ['quotes' => $quotes, 'not_offered' => $missing];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function zone(CarrierRateCard $card, string $country, string $postcode): ?array
    {
        $best      = null;
        $bestScore = -1;
        foreach ($card->zones as $zone) {
            foreach ($zone['match'] as $rule) {
                if (strtoupper($rule['country']) !== $country) {
                    continue;
                }
                $prefixes = $rule['postcodes'] ?? [];
                $score    = $prefixes ? max(array_map(fn (string $prefix) => str_starts_with($postcode, $prefix) ? strlen($prefix) : -1, $prefixes)) : 0;
                if ($score > $bestScore) {
                    $best      = $zone;
                    $bestScore = $score;
                }
            }
        }

        return $best;
    }

    /**
     * @param array<string, mixed> $zone
     */
    private function price(array $zone, float $chargeableKg): ?float
    {
        $bands = collect($zone['bands'])->mapWithKeys(fn ($price, $kg) => [(string) (float) $kg => (float) $price])->sortKeys(SORT_NUMERIC);

        foreach ($bands as $kg => $price) {
            if ($chargeableKg <= (float) $kg) {
                return $price;
            }
        }

        if ($bands->isEmpty() || $zone['extra_kg'] === null) {
            return null;
        }

        $topKg = (float) $bands->keys()->last();

        return $bands->last() + ceil($chargeableKg - $topKg) * (float) $zone['extra_kg'];
    }
}
