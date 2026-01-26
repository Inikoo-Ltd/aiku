<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class FilterByLocation
{
    /**
     * Apply the "By Location" filter to the query.
     *
     * @param Builder $query
     * @param array $value
     * @return Builder
     */
    public function apply($query, $value)
    {
        $location = Arr::get($value, 'location');
        $radius = Arr::get($value, 'radius');

        if (empty($location)) {
            return $query;
        }

        // TODO: Integrate with a Geocoding service (e.g., Google Maps API)
        // to convert the text location (e.g., "London", "SW1A 1AA") into coordinates.
        // For now, we simulate this or assume the input might be coordinates if formatted so.
        $coordinates = $this->geocodeLocation($location);

        if (! $coordinates) {
            return $query;
        }

        $lat = $coordinates['lat'];
        $lng = $coordinates['lng'];
        $radiusKm = $this->parseRadius($radius);

        return $query->whereHas('addresses', function (Builder $q) use ($lat, $lng, $radiusKm) {
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

    /**
     * Parse the radius string into kilometers.
     */
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

    /**
     * Placeholder for Geocoding logic.
     */
    private function geocodeLocation(string $location): ?array
    {
        return [
            'lat' => 51.5074,
            'lng' => -0.1278
        ];
    }
}
