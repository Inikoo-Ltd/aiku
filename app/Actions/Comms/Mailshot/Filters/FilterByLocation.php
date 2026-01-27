<?php

namespace App\Actions\Comms\Mailshot\Filters;

use App\Services\GeocoderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class FilterByLocation
{
    /**
     * Apply the "By Location" filter to the query.
     *
     * Supports two modes:
     * 1. Radius Mode: Using Geocoding + Haversine (Latitude/Longitude)
     * 2. Direct Mode: Using Country IDs and Postal Codes
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

                    $q->where(function ($subQ) use ($normalizedCodes) {
                        $subQ->whereRaw("REPLACE(UPPER(postal_code), ' ', '') IN ('" . implode("','", $normalizedCodes) . "')");
                    });
                }
            });
        }

        if (!empty($location)) {
            $coordinates = $this->geocodeLocation($location);

            if (! $coordinates) {
                return $query;
            }

            $lat = $coordinates['latitude'];
            $lng = $coordinates['longitude'];
            $radiusKm = $this->parseRadius($radius);

            return $query->whereHas('address', function (Builder $q) use ($lat, $lng, $radiusKm) {
                $q->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->whereRaw("
                      (6371 * acos(
                          cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) +
                          sin(radians(?)) * sin(radians(latitude))
                      )) <= ?
                  ", [$lat, $lng, $lat, $radiusKm]);
            });
        }

        return $query;
    }

    private function parseRadius($radius)
    {
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
        if (is_array($location)) {
            return $geocoder->geocodeLayered($location);
        }
        return $geocoder->geocode($location);
    }
}
