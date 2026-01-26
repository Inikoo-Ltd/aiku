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
    protected int $cacheTime = 129600; // 90 days in minutes

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

            // Rate limiting untuk Nominatim
            usleep(100000); // 0.1 detik
        }

        Log::warning('❌ Geocoding FAILED for all layers', [
            'address_data' => $addressData,
            'layers_tried' => count($layers),
        ]);

        return null;
    }

    /**
     * Build geocoding layers dari data address
     * Strategy: OTOMATIS generate SEMUA kombinasi dari spesifik ke general
     * Backend yang pintar - user tinggal kasih data apapun yang ada
     */
    protected function buildGeocodingLayers(array $data): array
    {
        $layers = [];

        // Normalize dan extract data - ambil SEMUA yang ada
        $parts = [
            'address_line_1' => trim($data['address_line_1'] ?? ''),
            'address_line_2' => trim($data['address_line_2'] ?? ''),
            'locality' => trim($data['locality'] ?? ''),
            'postal_code' => trim($data['postal_code'] ?? ''),
            'administrative_area' => trim($data['administrative_area'] ?? ''),
            'country_code' => strtoupper(trim($data['country_code'] ?? '')),
        ];

        // Filter hanya yang ada isinya
        $availableParts = array_filter($parts);

        // Helper function untuk build query
        $buildQuery = function (array $selectedParts) {
            return implode(', ', array_filter($selectedParts));
        };

        // STRATEGI: Generate kombinasi dari LENGKAP ke MINIMAL
        // Semakin banyak parts = semakin spesifik = confidence tinggi

        // Level 1: ALL PARTS (paling spesifik)
        if (count($availableParts) >= 4) {
            $layers[] = [
                'name' => 'all_parts',
                'query' => $buildQuery($availableParts),
                'confidence_base' => 95,
            ];
        }

        // Level 2: Tanpa address_line_2
        $withoutLine2 = $availableParts;
        unset($withoutLine2['address_line_2']);
        if (count($withoutLine2) >= 3) {
            $layers[] = [
                'name' => 'without_line2',
                'query' => $buildQuery($withoutLine2),
                'confidence_base' => 90,
            ];
        }

        // Level 3: Hanya address_line_1 + locality + country
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

        // Level 4: Locality + postal + country
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

        // Level 5: Locality + admin + country
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

        // Level 6: Locality + country (KUNCI - ini harusnya selalu berhasil)
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

        // Level 7: Admin area + country
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

        // Level 8: Postal + country
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

        // Level 9: Country only (FALLBACK FINAL)
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

    /**
     * Convert country code ke nama lengkap untuk geocoding lebih akurat
     */
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

    /**
     * Perform geocoding untuk satu layer
     */
    protected function performLayeredGeocode(array $layer): ?array
    {
        try {
            $query = GeocodeQuery::create($layer['query']);

            // JANGAN tambahkan country code constraint - biarkan API lebih fleksibel
            // Country akan divalidasi di hasil, bukan di query

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

            // Hitung confidence score berdasarkan detail yang tersedia
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

    /**
     * Calculate confidence score berdasarkan detail yang tersedia
     */
    protected function calculateConfidenceScore($location, int $baseScore): int
    {
        $score = $baseScore;

        // Bonus untuk data yang lebih detail
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

    /**
     * Build formatted address dari location result
     */
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

    /**
     * Normalize address string
     */
    protected function normalizeAddress(string $address): string
    {
        // Remove extra whitespace
        $address = preg_replace('/\s+/', ' ', $address);

        // Remove multiple commas
        $address = preg_replace('/,+/', ',', $address);

        // Remove leading/trailing commas and spaces
        $address = trim($address, ', ');

        return $address;
    }

    /**
     * Reverse geocode (convert coordinates to address)
     */
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

    /**
     * Backward compatibility: geocode with string
     */
    public function geocode(string $address, bool $enableFallback = true): ?array
    {
        // Parse string address ke array format
        $parts = array_map('trim', explode(',', $address));

        $addressData = [
            'address_line_1' => $parts[0] ?? '',
            'locality' => $parts[1] ?? null,
            'postal_code' => $this->extractPostalCode($address),
            'country_code' => $parts[count($parts) - 1] ?? '',
        ];

        return $this->geocodeLayered($addressData);
    }

    /**
     * Extract postal code dari string
     */
    protected function extractPostalCode(string $text): ?string
    {
        // Pattern untuk postal code berbagai format
        $patterns = [
            '/\b\d{5}(?:-\d{4})?\b/', // US: 12345 atau 12345-6789
            '/\b[A-Z]\d[A-Z]\s?\d[A-Z]\d\b/i', // Canada: A1A 1A1
            '/\b[A-Z]{1,2}\d{1,2}\s?\d[A-Z]{2}\b/i', // UK: SW1A 1AA
            '/\b\d{4,6}\b/', // Generic 4-6 digits
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[0]);
            }
        }

        return null;
    }
}
