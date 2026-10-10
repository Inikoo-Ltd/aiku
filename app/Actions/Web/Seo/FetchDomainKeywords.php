<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Models\Web\SeoDomainKeyword;
use App\Models\Web\SeoDomainOverview;
use App\Models\Web\Website;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\DataForSeoException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The keywords a domain ranks for in the top 100 of one market (up to 1,000, highest volume first)
 * and its organic summary, from one DataForSEO Labs ranked_keywords request. A domain fetched in
 * the last four weeks is not fetched again, so typed-in domains cost once a month at most.
 */
class FetchDomainKeywords
{
    use AsObject;

    public const int FRESH_DAYS = 27;

    public const int MAX_KEYWORDS = 1000;

    /**
     * @throws ValidationException
     * @throws DataForSeoException
     */
    public function handle(string $domain, string $countryCode, string $languageCode, ?Website $website = null, bool $fetchIfMissing = true): ?SeoDomainOverview
    {
        $domain       = StoreSerpResult::normaliseDomain($domain);
        $countryCode  = strtoupper($countryCode);
        $languageCode = strtolower($languageCode);

        $overview = SeoDomainOverview::where('domain', $domain)
            ->where('country_code', $countryCode)
            ->where('language_code', $languageCode)
            ->orderByDesc('date')
            ->first();

        if (($overview && $overview->date->gte(today()->subDays(self::FRESH_DAYS))) || !$fetchIfMissing || $domain === '') {
            return $overview;
        }

        $client = DataForSeoClient::make();

        if (!$client) {
            return $overview;
        }

        [$locationCode, $labsLanguageCode] = GetKeywordIdeas::make()->location($client, $countryCode, $languageCode);

        $result = Arr::first($client->live('dataforseo_labs/google/ranked_keywords/live', [
            'target'        => $domain,
            'location_code' => $locationCode,
            'language_code' => $labsLanguageCode,
            'limit'         => self::MAX_KEYWORDS,
            'order_by'      => ['keyword_data.keyword_info.search_volume,desc'],
        ], $website)) ?? [];

        $now  = now();
        $rows = collect(Arr::get($result, 'items') ?? [])
            ->map(fn (array $item) => [
                'domain'             => $domain,
                'country_code'       => $countryCode,
                'language_code'      => $languageCode,
                'keyword'            => Str::limit(Str::lower((string) Arr::get($item, 'keyword_data.keyword')), 255, ''),
                'position'           => (int) Arr::get($item, 'ranked_serp_element.serp_item.rank_group'),
                'url'                => Arr::get($item, 'ranked_serp_element.serp_item.url'),
                'search_volume'      => Arr::get($item, 'keyword_data.keyword_info.search_volume'),
                'estimated_traffic'  => Arr::get($item, 'ranked_serp_element.serp_item.etv'),
                'keyword_difficulty' => Arr::get($item, 'keyword_data.keyword_properties.keyword_difficulty') ?: null,
                'intent'             => Arr::get($item, 'keyword_data.search_intent_info.main_intent'),
                'fetched_at'         => $now,
                'created_at'         => $now,
                'updated_at'         => $now,
            ])
            ->filter(fn (array $row) => $row['keyword'] !== '' && $row['position'] > 0)
            ->unique('keyword')
            ->values();

        $organic = Arr::get($result, 'metrics.organic') ?? [];

        return DB::transaction(function () use ($domain, $countryCode, $languageCode, $rows, $organic) {
            SeoDomainKeyword::where('domain', $domain)
                ->where('country_code', $countryCode)
                ->where('language_code', $languageCode)
                ->delete();

            foreach ($rows->chunk(500) as $chunk) {
                SeoDomainKeyword::insert($chunk->all());
            }

            return SeoDomainOverview::updateOrCreate(
                ['domain' => $domain, 'country_code' => $countryCode, 'language_code' => $languageCode, 'date' => today()],
                [
                    'organic_keywords'  => (int) Arr::get($organic, 'count', 0),
                    'estimated_traffic' => round((float) Arr::get($organic, 'etv', 0), 2),
                    'top_3'             => (int) Arr::get($organic, 'pos_1', 0) + (int) Arr::get($organic, 'pos_2_3', 0),
                    'top_10'            => (int) Arr::get($organic, 'pos_1', 0) + (int) Arr::get($organic, 'pos_2_3', 0) + (int) Arr::get($organic, 'pos_4_10', 0),
                    'stored_keywords'   => $rows->count(),
                ]
            );
        });
    }
}
