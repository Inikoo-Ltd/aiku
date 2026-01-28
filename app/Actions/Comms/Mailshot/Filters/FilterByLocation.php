<?php

namespace App\Actions\Comms\Mailshot\Filters;

use App\Services\GeocoderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class FilterByLocation
{
    /**
     *
     * @param Builder $query
     * @param array $value
     * @return Builder
     */
    public function apply($query, $value)
    {
        $mode = Arr::get($value, 'mode', 'radius');
        $countryIds = Arr::get($value, 'country_ids', []);
        $postalCodes = Arr::get($value, 'postal_codes', []);
        $location = Arr::get($value, 'location');
        $radius = Arr::get($value, 'radius');


        if ($mode === 'direct' || (!empty($countryIds) || !empty($postalCodes))) {
            return $query->whereHas('address', function (Builder $q) use ($countryIds, $postalCodes) {
                if (!empty($countryIds)) {
                    $q->whereIn('country_id', $countryIds);
                }
                if (!empty($postalCodes)) {
                    $normalizedCodes = array_map(function ($code) {
                        return strtoupper(preg_replace('/\s+/', '', $code));
                    }, $postalCodes);

                    $placeholders = implode(',', array_fill(0, count($normalizedCodes), '?'));
                    $q->whereRaw("REPLACE(UPPER(postal_code), ' ', '') IN ($placeholders)", $normalizedCodes);
                }
            });
        }

        // --- MODE 2: RADIUS / AREA (Geocoding) ---
        if (!empty($location)) {
            $coordinates = $this->geocodeLocation($location);
            if (!$coordinates) {
                throw ValidationException::withMessages([
                    'location' => __('Location not found. Please check the address and try again.'),
                ]);
            }

            // OPSI A: Whole Area (Bounding Box Search)
            if (($radius === 'area' || empty($radius)) && !empty($coordinates['bounds'])) {
                $bounds = $coordinates['bounds'];
                return $query->whereHas('address', function (Builder $q) use ($bounds) {
                    $q->whereBetween('latitude', [$bounds['south'], $bounds['north']])
                        ->whereBetween('longitude', [$bounds['west'], $bounds['east']]);
                });
            }

            // OPSI B: Radius Search (Haversine)
            $lat = $coordinates['latitude'];
            $lng = $coordinates['longitude'];
            $radiusKm = $this->parseRadius($radius);

            return $query->whereHas('address', function (Builder $q) use ($lat, $lng, $radiusKm) {
                $q->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->whereRaw("
                      (6371 * acos(
                          LEAST(1.0, GREATEST(-1.0,
                              cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) +
                              sin(radians(?)) * sin(radians(latitude))
                          ))
                      )) <= ?
                  ", [$lat, $lng, $lat, $radiusKm]);
            });
        }

        return $query;
    }

    private function parseRadius($radius)
    {
        if ($radius === 'area') {
            return 20;
        }
        if (empty($radius)) {
            return 10;
        }
        if ($radius === 'custom') {
            return 50;
        }

        return (int) filter_var($radius, FILTER_SANITIZE_NUMBER_INT) ?: 10;
    }

    private function geocodeLocation($location): ?array
    {
        $geocoder = new GeocoderService();
        return $geocoder->geocode($location);
    }
}
