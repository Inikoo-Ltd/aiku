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

    private const string LLM_SCRAPER_CACHE_KEY = 'dataforseo:llm_scraper_locations';

    private const string LLM_MENTIONS_CACHE_KEY = 'dataforseo:llm_mentions_locations';

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

    /**
     * The ChatGPT LLM Scraper takes any of its 192 countries with any of its languages.
     *
     * @return array{location_code: int, languages: array<int, string>}|null
     * @throws DataForSeoException
     */
    public static function forLlmScraper(DataForSeoClient $client, string $countryCode): ?array
    {
        $scraper = Cache::get(self::LLM_SCRAPER_CACHE_KEY);

        if (!$scraper) {
            $languages = collect($client->get('ai_optimization/chat_gpt/llm_scraper/languages'))->pluck('language_code')->map(fn ($code) => strtolower($code))->values()->all();
            $scraper   = [
                'languages' => $languages,
                'locations' => collect($client->get('ai_optimization/chat_gpt/llm_scraper/locations'))
                    ->filter(fn (array $location) => Arr::get($location, 'country_iso_code'))
                    ->mapWithKeys(fn (array $location) => [strtoupper($location['country_iso_code']) => (int) $location['location_code']])
                    ->all(),
            ];

            if ($scraper['locations'] && $languages) {
                Cache::put(self::LLM_SCRAPER_CACHE_KEY, $scraper, now()->addDays(30));
            }
        }

        $locationCode = $scraper['locations'][strtoupper($countryCode)] ?? null;

        return $locationCode ? ['location_code' => $locationCode, 'languages' => $scraper['languages']] : null;
    }

    /**
     * The markets LLM Mentions has AI answers for, by location code, each with its languages and the
     * platforms per language.
     *
     * @return array<int, array<string, array<int, string>>>
     * @throws DataForSeoException
     */
    public static function forLlmMentions(DataForSeoClient $client): array
    {
        $locations = Cache::get(self::LLM_MENTIONS_CACHE_KEY);

        if ($locations) {
            return $locations;
        }

        $locations = collect($client->get('ai_optimization/llm_mentions/locations_and_languages'))
            ->mapWithKeys(fn (array $location) => [
                (int) $location['location_code'] => collect(Arr::get($location, 'available_languages', []))
                    ->mapWithKeys(fn (array $language) => [strtolower($language['language_code']) => Arr::get($language, 'available_platforms', [])])
                    ->all(),
            ])
            ->all();

        if ($locations) {
            Cache::put(self::LLM_MENTIONS_CACHE_KEY, $locations, now()->addDays(30));
        }

        return $locations;
    }
}
