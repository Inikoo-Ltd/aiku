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
     * @param array $filters
     * @return Builder
     */
    public function apply($query, array $filters)
    {
        $locationFilter = Arr::get($filters, 'by_location');
        $locationValue = is_array($locationFilter) ? ($locationFilter['value'] ?? []) : [];

        if (
            empty(Arr::get($locationValue, 'location')) &&
            empty(Arr::get($locationValue, 'country_ids')) &&
            empty(Arr::get($locationValue, 'postal_codes'))
        ) {
            return $query;
        }

        $mode = Arr::get($locationValue, 'mode', 'radius');
        $countryIds = Arr::get($locationValue, 'country_ids', []);
        $postalCodes = Arr::get($locationValue, 'postal_codes', []);
        $location = Arr::get($locationValue, 'location');
        $lat = Arr::get($locationValue, 'lat');
        $lng = Arr::get($locationValue, 'lng');

        $rawRadius = Arr::get($locationValue, 'radius');
        $rawRadiusCustom = Arr::get($locationValue, 'radius_custom');

        $isWholeAreaMode = false;

        if ($rawRadius === 'custom') {
            if (empty($rawRadiusCustom)) {
                $isWholeAreaMode = true;
                $radiusKm = null;
            } else {
                $radiusKm = (int) $rawRadiusCustom;
            }
        } elseif (empty($rawRadius)) {
            $isWholeAreaMode = true;
            $radiusKm = null;
        } else {
            $radiusKm = (int) $rawRadius;
        }


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
        if ($lat !== null && $lng !== null) {

            $coordinates =  $this->geocodeLocation($location);

            if (!$coordinates) {
                throw ValidationException::withMessages([
                    'location' => __('Location not found. Please check the address and try again.'),
                ]);
            }

            if ($isWholeAreaMode) {
                if (!empty($coordinates['bounds'])) {
                    $bounds = $coordinates['bounds'];
                    $south = (float) min($bounds['south'], $bounds['north']);
                    $north = (float) max($bounds['south'], $bounds['north']);
                    $west  = (float) min($bounds['west'],  $bounds['east']);
                    $east  = (float) max($bounds['west'],  $bounds['east']);

                    return $query->whereHas('address', function (Builder $q) use ($south, $north, $west, $east) {
                        $q->whereNotNull('latitude')
                            ->whereNotNull('longitude')
                            ->where('latitude', '>=', $south)
                            ->where('latitude', '<=', $north)
                            ->where('longitude', '>=', $west)
                            ->where('longitude', '<=', $east);
                    });
                }

                if (!empty($coordinates['city'])) {
                    $city = $coordinates['city'];
                    return $query->whereHas('address', function (Builder $q) use ($city) {
                        $q->whereNotNull('latitude')
                            ->where('geocoding_metadata->city', $city);
                    });
                }
                $radiusKm = 20;
            }


            $lat = $coordinates['latitude'];
            $lng = $coordinates['longitude'];

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

    private function reverseGeocodeLocation($lat, $lng): ?array
    {
        $geocoder = new GeocoderService();
        return $geocoder->reverseGeocode($lat, $lng);
    }
}
