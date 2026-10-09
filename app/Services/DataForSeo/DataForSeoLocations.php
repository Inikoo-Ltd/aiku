<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Services\DataForSeo;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * DataForSEO addresses countries by Google's geo target id (2826 for the United Kingdom) and lists
 * per country the languages it has keyword data for. The list is free to fetch and rarely changes.
 */
class DataForSeoLocations
{
    private const string CACHE_KEY = 'dataforseo:labs_locations';

    /**
     * @return array{location_code: int, languages: array<int, string>}|null
     * @throws DataForSeoException
     */
    public static function forCountry(DataForSeoClient $client, string $countryCode): ?array
    {
        return self::all($client)[strtoupper($countryCode)] ?? null;
    }

    /**
     * @return array<string, array{location_code: int, languages: array<int, string>}>
     * @throws DataForSeoException
     */
    private static function all(DataForSeoClient $client): array
    {
        $locations = Cache::get(self::CACHE_KEY);

        if ($locations) {
            return $locations;
        }

        $locations = collect($client->get('dataforseo_labs/locations_and_languages'))
            ->filter(fn (array $location) => Arr::get($location, 'location_type') === 'Country' && Arr::get($location, 'country_iso_code'))
            ->mapWithKeys(fn (array $location) => [
                strtoupper($location['country_iso_code']) => [
                    'location_code' => (int) $location['location_code'],
                    'languages'     => collect(Arr::get($location, 'available_languages', []))->pluck('language_code')->map(fn ($code) => strtolower($code))->values()->all(),
                ],
            ])
            ->all();

        if ($locations) {
            Cache::put(self::CACHE_KEY, $locations, now()->addDays(30));
        }

        return $locations;
    }
}
