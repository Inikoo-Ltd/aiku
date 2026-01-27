<?php

namespace App\Services;

use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\Provider\Nominatim\Nominatim;
use Geocoder\StatefulGeocoder;
use Http\Adapter\Guzzle7\Client as GuzzleAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Helpers\Country;

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
     * Geocode address string to coordinates with Layered Fallback
     */
    public function geocodeLayered(array $addressData): ?array
    {
        $layers = $this->buildGeocodingLayers($addressData);


        Log::info('📍 Geocoding started', [
            'input' => $addressData,
            'total_layers' => count($layers),
            'layers' => array_map(fn ($l) => $l['name'] . ': ' . $l['query'], $layers),
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

        Log::warning('⏭ Geocoding FAILED for all layers', [
            'address_data' => $addressData,
            'layers_tried' => count($layers),
        ]);

        return null;
    }


    protected function buildGeocodingLayers(array $data): array
    {
        $layers = [];

        $street = trim($data['address_line_1'] ?? '');
        if (empty($street)) {
            $street = trim($data['address_line_2'] ?? '');
        }

        $parts = [
            'street'              => $street,
            'dependent_locality'  => $data['dependent_locality'] ?? '',
            'locality'            => $data['locality'] ?? '',
            'postal_code'         => trim($data['postal_code'] ?? ''),
            'administrative_area' => $data['administrative_area'] ?? '',
            'country_name'        => $data['country_name'] ?? '',
        ];

        $buildQuery = function (array $selectedParts) {
            return implode(', ', array_filter($selectedParts));
        };

        if (!empty($parts['street']) && !empty($parts['locality']) && !empty($parts['administrative_area']) && !empty($parts['country_name'])) {
            $layers[] = [
                'name' => 'full_specific',
                'query' => $buildQuery([
                    $parts['street'],
                    $parts['locality'],
                    $parts['administrative_area'],
                    $parts['country_name'],
                ]),
                'confidence_base' => 95,
            ];
        }

        if (!empty($parts['street']) && !empty($parts['locality']) && !empty($parts['country_name'])) {
            $layers[] = [
                'name' => 'street_city_country',
                'query' => $buildQuery([
                    $parts['street'],
                    $parts['locality'],
                    $parts['country_name'],
                ]),
                'confidence_base' => 85,
            ];
        }

        if (!empty($parts['dependent_locality']) && !empty($parts['locality']) && !empty($parts['administrative_area']) && !empty($parts['country_name'])) {
            $layers[] = [
                'name' => 'district_level',
                'query' => $buildQuery([
                    $parts['dependent_locality'],
                    $parts['locality'],
                    $parts['administrative_area'],
                    $parts['country_name'],
                ]),
                'confidence_base' => 80,
            ];
        }

        if (!empty($parts['locality']) && !empty($parts['administrative_area']) && !empty($parts['country_name'])) {
            $layers[] = [
                'name' => 'city_province_level',
                'query' => $buildQuery([
                    $parts['locality'],
                    $parts['administrative_area'],
                    $parts['country_name'],
                ]),
                'confidence_base' => 65,
            ];
        }

        if (!empty($parts['locality']) && !empty($parts['country_name'])) {
            $layers[] = [
                'name' => 'city_only',
                'query' => $buildQuery([
                    $parts['locality'],
                    $parts['country_name'],
                ]),
                'confidence_base' => 55,
            ];
        }

        if (!empty($parts['postal_code']) && !empty($parts['country_name'])) {
            $layers[] = [
                'name' => 'postal_code_level',
                'query' => $buildQuery([
                    $parts['postal_code'],
                    $parts['country_name'],
                ]),
                'confidence_base' => 60,
            ];
        }

        if (!empty($parts['country_name'])) {
            $layers[] = [
                'name' => 'country_level',
                'query' => $parts['country_name'],
                'confidence_base' => 30,
            ];
        }

        return $layers;
    }


    protected function getCountryName(string $code): ?string
    {
        return Cache::remember("country_name_{$code}", 86400, function () use ($code) {
            return Country::where('code', $code)->value('name');
        });
    }



    protected function performLayeredGeocode(array $layer): ?array
    {
        try {
            $query = GeocodeQuery::create($layer['query']);
            $results = $this->geocoder->geocodeQuery($query);

            if ($results->isEmpty()) {
                Log::info('⏭ Layer gagal', [
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
            $score += 5;
        } // Poin plus untuk kode pos
        if ($location->getLocality()) {
            $score += 2;
        }
        if ($location->getSubLocality()) {
            $score += 3;
        } // Poin plus untuk kecamatan/sub-district

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
        return trim($address, ', ');
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
                return null;
            }
        });
    }


    public function geocode(string $address, bool $enableFallback = true): ?array
    {

        $queryText = trim($address);
        if (empty($queryText)) {
            return null;
        }

        $cacheKey = 'geocode:simple:' . md5($queryText);

        return Cache::remember($cacheKey, $this->cacheTime, function () use ($queryText) {
            try {

                $query = GeocodeQuery::create($queryText);


                $results = $this->geocoder->geocodeQuery($query);

                if ($results->isEmpty()) {
                    return null;
                }


                $location = $results->first();
                $coordinates = $location->getCoordinates();
                $bounds = $location->getBounds();


                return [
                    'latitude'          => $coordinates->getLatitude(),
                    'longitude'         => $coordinates->getLongitude(),
                    'formatted_address' => $this->buildFormattedAddress($location),
                    'matched_layer'     => 'simple_query',
                    'confidence_score'  => 80,
                    'street'            => $location->getStreetName(),
                    'city'              => $location->getLocality() ?? $location->getSubLocality(),
                    'postal_code'       => $location->getPostalCode(),
                    'country'           => $location->getCountry()?->getName(),
                    'country_code'      => $location->getCountry()?->getCode(),
                    'bounds'            => $bounds ? [
                        'south' => $bounds->getSouth(),
                        'west'  => $bounds->getWest(),
                        'north' => $bounds->getNorth(),
                        'east'  => $bounds->getEast(),
                    ] : null,
                ];
            } catch (\Exception $e) {
                Log::error('Simple geocoding failed', [
                    'query' => $queryText,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        });
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
