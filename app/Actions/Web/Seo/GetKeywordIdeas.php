<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Models\Catalogue\Shop;
use App\Models\Web\SeoKeyword;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\DataForSeoException;
use App\Services\DataForSeo\DataForSeoLocations;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

class GetKeywordIdeas
{
    use AsObject;

    public const int MAX_IDEAS = 300;

    public const int MAX_SEED_KEYWORDS = 20;

    private const int CACHE_HOURS = 24;

    /**
     * Seed keywords bring their own figures and related keyword ideas; a URL brings the keywords
     * that page ranks for in Google.
     *
     * @param  array<int, string>  $seedKeywords
     * @return array{ideas: array<int, array>}
     * @throws ValidationException
     */
    public function handle(Shop $shop, array $seedKeywords, ?string $url, string $countryCode, string $languageCode): array
    {
        $seedKeywords = array_values(array_slice(array_unique(array_filter(array_map(fn ($keyword) => Str::lower(trim($keyword)), $seedKeywords))), 0, self::MAX_SEED_KEYWORDS));
        $url          = $url ? trim($url) : null;
        $countryCode  = strtoupper($countryCode);
        $languageCode = strtolower($languageCode);

        if ($seedKeywords === [] && !$url) {
            throw ValidationException::withMessages(['seed' => __('Enter at least one keyword or a URL.')]);
        }

        $client = DataForSeoClient::make();

        if (!$client) {
            throw ValidationException::withMessages(['seed' => __('DataForSEO is not set up. Add DATAFORSEO_LOGIN and DATAFORSEO_PASSWORD to the environment.')]);
        }

        $cacheKey = 'seo:keyword_ideas:'.md5(json_encode([$seedKeywords, $url, $countryCode, $languageCode]));

        try {
            $ideas = Cache::get($cacheKey);

            if ($ideas === null) {
                $ideas = $this->fetchIdeas($client, $shop, $seedKeywords, $url, ...$this->location($client, $countryCode, $languageCode));

                $this->store($shop, $ideas, $countryCode, $languageCode);

                Cache::put($cacheKey, $ideas, now()->addHours(self::CACHE_HOURS));
            }
        } catch (DataForSeoException $e) {
            throw ValidationException::withMessages(['seed' => $e->getMessage()]);
        }

        return ['ideas' => $ideas];
    }

    /**
     * @return array{0: int, 1: string}
     * @throws ValidationException
     * @throws DataForSeoException
     */
    public function location(DataForSeoClient $client, string $countryCode, string $languageCode): array
    {
        $location = DataForSeoLocations::forCountry($client, $countryCode);

        if (!$location) {
            throw ValidationException::withMessages(['country_code' => __('DataForSEO has no keyword data for this country.')]);
        }

        $languageCode = str_replace('_', '-', $languageCode);

        foreach ([$languageCode, Str::before($languageCode, '-')] as $candidate) {
            if (in_array($candidate, $location['languages'], true)) {
                return [$location['location_code'], $candidate];
            }
        }

        throw ValidationException::withMessages(['language_code' => __('DataForSEO has no keyword data in this language for this country. It has: :languages.', ['languages' => implode(', ', $location['languages'])])]);
    }

    /**
     * @param  array<int, string>  $seedKeywords
     * @return array<int, array>
     * @throws DataForSeoException
     */
    private function fetchIdeas(DataForSeoClient $client, Shop $shop, array $seedKeywords, ?string $url, int $locationCode, string $languageCode): array
    {
        $location = ['location_code' => $locationCode, 'language_code' => $languageCode];
        $items    = [];

        if ($seedKeywords) {
            $items = [
                ...Arr::get($client->live('dataforseo_labs/google/keyword_overview/live', [...$location, 'keywords' => $seedKeywords, 'include_serp_info' => false], $shop->website), '0.items') ?? [],
                ...Arr::get($client->live('dataforseo_labs/google/keyword_ideas/live', [...$location, 'keywords' => $seedKeywords, 'limit' => self::MAX_IDEAS, 'order_by' => ['keyword_info.search_volume,desc']], $shop->website), '0.items') ?? [],
            ];
        }

        if ($url) {
            $rankedKeywords = Arr::get($client->live('dataforseo_labs/google/ranked_keywords/live', [...$location, 'target' => $url, 'limit' => self::MAX_IDEAS, 'order_by' => ['keyword_data.keyword_info.search_volume,desc']], $shop->website), '0.items') ?? [];

            array_push($items, ...array_map(fn (array $item) => Arr::get($item, 'keyword_data', []), $rankedKeywords));
        }

        return collect($items)
            ->map(fn (array $item) => self::idea($item))
            ->filter(fn (array $idea) => $idea['keyword'] !== '' && mb_strlen($idea['keyword']) <= 255)
            ->unique('keyword')
            ->sortBy(fn (array $idea) => [in_array($idea['keyword'], $seedKeywords, true) ? 0 : 1, -($idea['avg_monthly_searches'] ?? -1)])
            ->take(self::MAX_IDEAS)
            ->values()
            ->all();
    }

    /**
     * One DataForSEO Labs keyword item, as stored in `seo_keywords` and shown on the Research tab.
     */
    public static function idea(array $item): array
    {
        $competition = Arr::get($item, 'keyword_info.competition');

        return [
            'keyword'              => Str::lower((string) Arr::get($item, 'keyword')),
            'avg_monthly_searches' => Arr::get($item, 'keyword_info.search_volume'),
            'monthly_searches'     => collect(Arr::get($item, 'keyword_info.monthly_searches') ?? [])
                ->map(fn (array $month) => [
                    'year'     => (int) Arr::get($month, 'year'),
                    'month'    => (int) Arr::get($month, 'month'),
                    'searches' => (int) Arr::get($month, 'search_volume', 0),
                ])
                ->sortBy(fn (array $month) => $month['year'] * 100 + $month['month'])
                ->values()
                ->all(),
            'competition'          => Arr::get($item, 'keyword_info.competition_level'),
            'competition_index'    => is_numeric($competition) ? (int) round($competition * 100) : null,
            'cpc'                  => Arr::get($item, 'keyword_info.cpc'),
            'low_top_of_page_bid'  => Arr::get($item, 'keyword_info.low_top_of_page_bid'),
            'high_top_of_page_bid' => Arr::get($item, 'keyword_info.high_top_of_page_bid'),
            'keyword_difficulty'   => Arr::get($item, 'keyword_properties.keyword_difficulty'),
            'intent'               => Arr::get($item, 'search_intent_info.main_intent'),
            'secondary_intents'    => array_values(array_filter(Arr::wrap(Arr::get($item, 'search_intent_info.foreign_intent')))),
        ];
    }

    private function store(Shop $shop, array $ideas, string $countryCode, string $languageCode): void
    {
        $now = now();

        foreach (array_chunk($ideas, 500) as $chunk) {
            SeoKeyword::upsert(
                array_map(fn (array $idea) => [
                    'shop_id'              => $shop->id,
                    'keyword'              => $idea['keyword'],
                    'country_code'         => $countryCode,
                    'language_code'        => $languageCode,
                    'avg_monthly_searches' => $idea['avg_monthly_searches'],
                    'monthly_searches'     => json_encode($idea['monthly_searches']),
                    'competition'          => $idea['competition'],
                    'competition_index'    => $idea['competition_index'],
                    'cpc'                  => $idea['cpc'],
                    'low_top_of_page_bid'  => $idea['low_top_of_page_bid'],
                    'high_top_of_page_bid' => $idea['high_top_of_page_bid'],
                    'keyword_difficulty'   => $idea['keyword_difficulty'],
                    'intent'               => $idea['intent'],
                    'secondary_intents'    => json_encode($idea['secondary_intents']),
                    'intent_source'        => $idea['intent'] ? SeoKeyword::SOURCE_DATAFORSEO_LABS : null,
                    'source'               => SeoKeyword::SOURCE_DATAFORSEO_LABS,
                    'fetched_at'           => $now,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ], $chunk),
                ['shop_id', 'keyword', 'country_code', 'language_code'],
                ['avg_monthly_searches', 'monthly_searches', 'competition', 'competition_index', 'cpc', 'low_top_of_page_bid', 'high_top_of_page_bid', 'keyword_difficulty', 'intent', 'secondary_intents', 'intent_source', 'source', 'fetched_at', 'updated_at']
            );
        }
    }
}
