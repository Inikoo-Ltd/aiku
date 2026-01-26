<?php

namespace App\Services;

use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\Provider\Nominatim\Nominatim;
use Geocoder\StatefulGeocoder;
use Http\Adapter\Guzzle7\Client as GuzzleAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeocoderService
{
    protected StatefulGeocoder $geocoder;
    protected string $provider;
    protected int $cacheTime = 129600;

    public function __construct()
    {
        $this->provider = config('services.geocoding.provider', 'nominatim');

        $httpClient = new GuzzleAdapter();

        $provider = new Nominatim(
            $httpClient,
            'https://nominatim.openstreetmap.org',
            config('app.name', 'Laravel App') . ' (' . config('mail.from.address', 'noreply@inikoo.com') . ')'
        );

        $this->geocoder = new StatefulGeocoder($provider, 'en');
    }

    /**
     * Geocode address string to coordinates
     */
     public function geocodeLayered(array $addressData): ?array
    {
        $layers = $this->buildGeocodingLayers($addressData);

        Log::info('🔍 Geocoding started', [
            'input' => $addressData,
            'total_layers' => count($layers),
            'layers' => array_map(fn($l) => $l['name'] . ': ' . $l['query'], $layers),
        ]);

        foreach ($layers as $index => $layer) {
            Log::info("🔄 Trying layer " . ($index + 1) . "/" . count($layers), [
                'layer' => $layer['name'],
                'query' => $layer['query'],
            ]);

            $cacheKey = 'geocode:layered:' . md5($layer['query']);

            $result = Cache::remember($cacheKey, $this->cacheTime, function () use ($layer) {
                return $this->performLayeredGeocode($layer);
            });

            if ($result) {
                Log::info('✅ Geocoding SUCCESS', [
                    'layer' => $layer['name'],
                    'query' => $layer['query'],
                    'confidence' => $result['confidence_score'],
                    'coordinates' => $result['latitude'] . ', ' . $result['longitude'],
                ]);

                return $result;
            }

            usleep(100000);
        }

        Log::warning('❌ Geocoding FAILED for all layers', [
            'address_data' => $addressData,
            'layers_tried' => count($layers),
        ]);

        return null;
    }


    protected function buildGeocodingLayers(array $data): array
    {
        $layers = [];

        $parts = [
            'address_line_1' => trim($data['address_line_1'] ?? ''),
            'address_line_2' => trim($data['address_line_2'] ?? ''),
            'locality' => trim($data['locality'] ?? ''),
            'postal_code' => trim($data['postal_code'] ?? ''),
            'administrative_area' => trim($data['administrative_area'] ?? ''),
            'country_code' => strtoupper(trim($data['country_code'] ?? '')),
        ];

        $availableParts = array_filter($parts);

        $buildQuery = function(array $selectedParts) {
            return implode(', ', array_filter($selectedParts));
        };


        if (count($availableParts) >= 4) {
            $layers[] = [
                'name' => 'all_parts',
                'query' => $buildQuery($availableParts),
                'confidence_base' => 95,
            ];
        }

        $withoutLine2 = $availableParts;
        unset($withoutLine2['address_line_2']);
        if (count($withoutLine2) >= 3) {
            $layers[] = [
                'name' => 'without_line2',
                'query' => $buildQuery($withoutLine2),
                'confidence_base' => 90,
            ];
        }

        if (!empty($parts['address_line_1']) && !empty($parts['locality']) && !empty($parts['country_code'])) {
            $layers[] = [
                'name' => 'address_locality_country',
                'query' => $buildQuery([
                    $parts['address_line_1'],
                    $parts['locality'],
                    $parts['country_code'],
                ]),
                'confidence_base' => 80,
            ];
        }

        if (!empty($parts['locality']) && !empty($parts['postal_code']) && !empty($parts['country_code'])) {
            $layers[] = [
                'name' => 'locality_postal_country',
                'query' => $buildQuery([
                    $parts['locality'],
                    $parts['postal_code'],
                    $parts['country_code'],
                ]),
                'confidence_base' => 70,
            ];
        }

        if (!empty($parts['locality']) && !empty($parts['administrative_area']) && !empty($parts['country_code'])) {
            $layers[] = [
                'name' => 'locality_admin_country',
                'query' => $buildQuery([
                    $parts['locality'],
                    $parts['administrative_area'],
                    $parts['country_code'],
                ]),
                'confidence_base' => 65,
            ];
        }

        if (!empty($parts['locality']) && !empty($parts['country_code'])) {
            $layers[] = [
                'name' => 'locality_country',
                'query' => $buildQuery([
                    $parts['locality'],
                    $parts['country_code'],
                ]),
                'confidence_base' => 60,
            ];
        }

        if (!empty($parts['administrative_area']) && !empty($parts['country_code'])) {
            $layers[] = [
                'name' => 'admin_country',
                'query' => $buildQuery([
                    $parts['administrative_area'],
                    $parts['country_code'],
                ]),
                'confidence_base' => 50,
            ];
        }

        if (!empty($parts['postal_code']) && !empty($parts['country_code'])) {
            $layers[] = [
                'name' => 'postal_country',
                'query' => $buildQuery([
                    $parts['postal_code'],
                    $parts['country_code'],
                ]),
                'confidence_base' => 45,
            ];
        }

        if (!empty($parts['country_code'])) {
            $countryName = $this->getCountryName($parts['country_code']);
            $layers[] = [
                'name' => 'country_only',
                'query' => $countryName ?: $parts['country_code'],
                'confidence_base' => 30,
            ];
        }

        return $layers;
    }


    protected function getCountryName(string $code): ?string
    {
        $countries = [
            'ID' => 'Indonesia',
            'SG' => 'Singapore',
            'MY' => 'Malaysia',
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'AU' => 'Australia',
            'JP' => 'Japan',
            'CN' => 'China',
            'IN' => 'India',
            'TH' => 'Thailand',
            'PH' => 'Philippines',
            'VN' => 'Vietnam',
        ];

        return $countries[$code] ?? null;
    }


    protected function performLayeredGeocode(array $layer): ?array
    {
        try {
            $query = GeocodeQuery::create($layer['query']);



            $results = $this->geocoder->geocodeQuery($query);

            if ($results->isEmpty()) {
                Log::info('❌ Layer gagal', [
                    'layer' => $layer['name'],
                    'query' => $layer['query'],
                ]);
                return null;
            }

            $location = $results->first();
            $coordinates = $location->getCoordinates();


            $confidenceScore = $this->calculateConfidenceScore($location, $layer['confidence_base']);

            $bounds = $location->getBounds();

            return [
                'latitude' => $coordinates->getLatitude(),
                'longitude' => $coordinates->getLongitude(),
                'formatted_address' => $this->buildFormattedAddress($location),
                'matched_layer' => $layer['name'],
                'confidence_score' => $confidenceScore,
                'street' => $location->getStreetName(),
                'street_number' => $location->getStreetNumber(),
                'city' => $location->getLocality() ?? $location->getSubLocality(),
                'postal_code' => $location->getPostalCode(),
                'country' => $location->getCountry()?->getName(),
                'country_code' => $location->getCountry()?->getCode(),
                'administrative_area' => $location->getAdminLevels()->first()?->getName(),
                'bounds' => $bounds ? [
                    'south' => $bounds->getSouth(),
                    'west' => $bounds->getWest(),
                    'north' => $bounds->getNorth(),
                    'east' => $bounds->getEast(),
                ] : null,
            ];

        } catch (\Exception $e) {
            Log::debug('Geocoding exception for layer', [
                'layer' => $layer['name'],
                'query' => $layer['query'],
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }


    protected function calculateConfidenceScore($location, int $baseScore): int
    {
        $score = $baseScore;

        if ($location->getStreetNumber()) {
            $score += 5;
        }

        if ($location->getStreetName()) {
            $score += 5;
        }

        if ($location->getPostalCode()) {
            $score += 3;
        }

        if ($location->getLocality()) {
            $score += 2;
        }

        // Cap antara 0-100
        return max(0, min(100, $score));
    }


    protected function buildFormattedAddress($location): string
    {
        $parts = array_filter([
            $location->getStreetNumber(),
            $location->getStreetName(),
            $location->getLocality() ?? $location->getSubLocality(),
            $location->getPostalCode(),
            $location->getAdminLevels()->first()?->getName(),
            $location->getCountry()?->getName(),
        ]);

        return implode(', ', $parts);
    }


    protected function normalizeAddress(string $address): string
    {
        $address = preg_replace('/\s+/', ' ', $address);

        $address = preg_replace('/,+/', ',', $address);

        $address = trim($address, ', ');

        return $address;
    }


    public function reverseGeocode(float $lat, float $lng): ?array
    {
        $cacheKey = 'reverse_geocode:' . $lat . ':' . $lng;

        return Cache::remember($cacheKey, $this->cacheTime, function () use ($lat, $lng) {
            try {
                $query = ReverseQuery::fromCoordinates($lat, $lng);
                $results = $this->geocoder->reverseQuery($query);

                if ($results->isEmpty()) {
                    return null;
                }

                $location = $results->first();

                return [
                    'formatted_address' => $this->buildFormattedAddress($location),
                    'street' => $location->getStreetName(),
                    'street_number' => $location->getStreetNumber(),
                    'city' => $location->getLocality(),
                    'postal_code' => $location->getPostalCode(),
                    'country' => $location->getCountry()?->getName(),
                    'country_code' => $location->getCountry()?->getCode(),
                    'administrative_area' => $location->getAdminLevels()->first()?->getName(),
                ];

            } catch (\Exception $e) {
                Log::warning('Reverse geocoding failed', [
                    'lat' => $lat,
                    'lng' => $lng,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }


    public function geocode(string $address, bool $enableFallback = true): ?array
    {
        $parts = array_map('trim', explode(',', $address));

        $addressData = [
            'address_line_1' => $parts[0] ?? '',
            'locality' => $parts[1] ?? null,
            'postal_code' => $this->extractPostalCode($address),
            'country_code' => $parts[count($parts) - 1] ?? '',
        ];

        return $this->geocodeLayered($addressData);
    }


    protected function extractPostalCode(string $text): ?string
    {
        $patterns = [
            '/\b\d{5}(?:-\d{4})?\b/',
            '/\b[A-Z]\d[A-Z]\s?\d[A-Z]\d\b/i',
            '/\b[A-Z]{1,2}\d{1,2}\s?\d[A-Z]{2}\b/i',
            '/\b\d{4,6}\b/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[0]);
            }
        }

        return null;
    }
}