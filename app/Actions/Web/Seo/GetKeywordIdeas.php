<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Models\Catalogue\Shop;
use App\Models\Web\SeoApiRequest;
use App\Models\Web\SeoKeyword;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsException;
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
     * @param  array<int, string>  $seedKeywords
     * @return array{account_shop: string, ideas: array<int, array>}
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

        [$client, $accountShop] = $this->clientFor($shop);

        $cacheKey = 'seo:keyword_ideas:'.md5(json_encode([$accountShop->id, $seedKeywords, $url, $countryCode, $languageCode]));

        try {
            $ideas = Cache::remember($cacheKey, now()->addHours(self::CACHE_HOURS), function () use ($client, $shop, $seedKeywords, $url, $countryCode, $languageCode) {
                $ideas = $this->fetchIdeas($client, $shop, $seedKeywords, $url, $countryCode, $languageCode);

                $this->store($shop, $ideas, $countryCode, $languageCode);

                return $ideas;
            });
        } catch (GoogleAdsException $e) {
            throw ValidationException::withMessages(['seed' => $this->explain($e)]);
        }

        return [
            'account_shop' => $accountShop->name,
            'ideas'        => $ideas,
        ];
    }

    /**
     * @return array{0: GoogleAdsClient, 1: Shop}
     * @throws ValidationException
     */
    private function clientFor(Shop $shop): array
    {
        $client = GoogleAdsClient::forShop($shop);

        if ($client) {
            return [$client, $shop];
        }

        $connectedShops = Shop::where('group_id', $shop->group_id)
            ->where('id', '!=', $shop->id)
            ->whereNotNull('settings->google_ads->refresh_token')
            ->whereNotNull('settings->google_ads->customer_id')
            ->orderBy('id')
            ->get();

        foreach ($connectedShops as $connectedShop) {
            $client = GoogleAdsClient::forShop($connectedShop);

            if ($client) {
                return [$client, $connectedShop];
            }
        }

        throw ValidationException::withMessages(['seed' => GoogleAdsClient::unreachableReason($shop) ?? __('No shop is connected to Google Ads.')]);
    }

    /**
     * @param  array<int, string>  $seedKeywords
     * @return array<int, array>
     * @throws ValidationException
     * @throws GoogleAdsException
     */
    private function fetchIdeas(GoogleAdsClient $client, Shop $shop, array $seedKeywords, ?string $url, string $countryCode, string $languageCode): array
    {
        $body = [
            'language'             => $this->languageConstant($client, $languageCode),
            'geoTargetConstants'   => [$this->geoTargetConstant($client, $countryCode)],
            'includeAdultKeywords' => false,
            'keywordPlanNetwork'   => 'GOOGLE_SEARCH',
        ];

        if ($seedKeywords && $url) {
            $body['keywordAndUrlSeed'] = ['url' => $url, 'keywords' => $seedKeywords];
        } elseif ($url) {
            $body['urlSeed'] = ['url' => $url];
        } else {
            $body['keywordSeed'] = ['keywords' => $seedKeywords];
        }

        $startedAt = hrtime(true);

        try {
            $results = $client->generateKeywordIdeas($body);
        } catch (GoogleAdsException $e) {
            $this->log($shop, false, 0, $startedAt, $e->getMessage());

            throw $e;
        }

        $this->log($shop, true, count($results), $startedAt);

        return collect($results)
            ->map(fn (array $result) => [
                'keyword'                     => Str::lower((string) Arr::get($result, 'text')),
                'avg_monthly_searches'        => Arr::has($result, 'keywordIdeaMetrics.avgMonthlySearches') ? (int) Arr::get($result, 'keywordIdeaMetrics.avgMonthlySearches') : null,
                'monthly_searches'            => collect(Arr::get($result, 'keywordIdeaMetrics.monthlySearchVolumes', []))
                    ->map(fn (array $month) => [
                        'year'     => (int) Arr::get($month, 'year'),
                        'month'    => Arr::get($month, 'month'),
                        'searches' => (int) Arr::get($month, 'monthlySearches', 0),
                    ])
                    ->values()
                    ->all(),
                'competition'                 => Arr::get($result, 'keywordIdeaMetrics.competition'),
                'competition_index'           => Arr::has($result, 'keywordIdeaMetrics.competitionIndex') ? (int) Arr::get($result, 'keywordIdeaMetrics.competitionIndex') : null,
                'low_top_of_page_bid_micros'  => Arr::has($result, 'keywordIdeaMetrics.lowTopOfPageBidMicros') ? (int) Arr::get($result, 'keywordIdeaMetrics.lowTopOfPageBidMicros') : null,
                'high_top_of_page_bid_micros' => Arr::has($result, 'keywordIdeaMetrics.highTopOfPageBidMicros') ? (int) Arr::get($result, 'keywordIdeaMetrics.highTopOfPageBidMicros') : null,
            ])
            ->filter(fn (array $idea) => $idea['keyword'] !== '' && mb_strlen($idea['keyword']) <= 255)
            ->unique('keyword')
            ->sortByDesc('avg_monthly_searches')
            ->take(self::MAX_IDEAS)
            ->values()
            ->all();
    }

    private function explain(GoogleAdsException $e): string
    {
        if (str_contains(Str::lower($e->getMessage()), 'explorer access')) {
            return __('The Google Ads developer token has Explorer access, and Keyword Planner needs Basic access. Google grants it after an application in the Google Ads API Center. Until then, use the Search Console queries below.');
        }

        return $e->getMessage();
    }

    private function store(Shop $shop, array $ideas, string $countryCode, string $languageCode): void
    {
        $now = now();

        foreach (array_chunk($ideas, 500) as $chunk) {
            SeoKeyword::upsert(
                array_map(fn (array $idea) => [
                    'shop_id'                     => $shop->id,
                    'keyword'                     => $idea['keyword'],
                    'country_code'                => $countryCode,
                    'language_code'               => $languageCode,
                    'avg_monthly_searches'        => $idea['avg_monthly_searches'],
                    'monthly_searches'            => json_encode($idea['monthly_searches']),
                    'competition'                 => $idea['competition'],
                    'competition_index'           => $idea['competition_index'],
                    'low_top_of_page_bid_micros'  => $idea['low_top_of_page_bid_micros'],
                    'high_top_of_page_bid_micros' => $idea['high_top_of_page_bid_micros'],
                    'source'                      => SeoKeyword::SOURCE_KEYWORD_PLANNER,
                    'fetched_at'                  => $now,
                    'created_at'                  => $now,
                    'updated_at'                  => $now,
                ], $chunk),
                ['shop_id', 'keyword', 'country_code', 'language_code'],
                ['avg_monthly_searches', 'monthly_searches', 'competition', 'competition_index', 'low_top_of_page_bid_micros', 'high_top_of_page_bid_micros', 'source', 'fetched_at', 'updated_at']
            );
        }
    }

    /**
     * @throws ValidationException
     */
    private function geoTargetConstant(GoogleAdsClient $client, string $countryCode): string
    {
        $countryCode = preg_replace('/[^A-Z]/', '', $countryCode);

        $resourceName = Cache::rememberForever("google_ads:geo_target_constant:$countryCode", function () use ($client, $countryCode) {
            $rows = $client->search(
                "SELECT geo_target_constant.resource_name FROM geo_target_constant
                 WHERE geo_target_constant.country_code = '$countryCode'
                   AND geo_target_constant.target_type = 'Country'
                   AND geo_target_constant.status = 'ENABLED'"
            );

            return Arr::get($rows, '0.geoTargetConstant.resourceName');
        });

        if (!$resourceName) {
            Cache::forget("google_ads:geo_target_constant:$countryCode");

            throw ValidationException::withMessages(['country_code' => __('Google Ads does not recognise this country.')]);
        }

        return $resourceName;
    }

    /**
     * @throws ValidationException
     */
    private function languageConstant(GoogleAdsClient $client, string $languageCode): string
    {
        $languageCode = preg_replace('/[^a-z_-]/', '', $languageCode);

        $resourceName = Cache::rememberForever("google_ads:language_constant:$languageCode", function () use ($client, $languageCode) {
            $rows = $client->search(
                "SELECT language_constant.resource_name FROM language_constant
                 WHERE language_constant.code = '$languageCode'"
            );

            return Arr::get($rows, '0.languageConstant.resourceName');
        });

        if (!$resourceName) {
            Cache::forget("google_ads:language_constant:$languageCode");

            throw ValidationException::withMessages(['language_code' => __('Google Ads does not support this language.')]);
        }

        return $resourceName;
    }

    private function log(Shop $shop, bool $isSuccess, int $rows, int $startedAt, ?string $error = null): void
    {
        SeoApiRequest::create([
            'provider'    => SeoKeyword::SOURCE_KEYWORD_PLANNER,
            'endpoint'    => 'generateKeywordIdeas',
            'website_id'  => $shop->website?->id,
            'is_success'  => $isSuccess,
            'rows'        => $rows,
            'duration_ms' => intdiv(hrtime(true) - $startedAt, 1_000_000),
            'error'       => $error ? Str::limit($error, 2000) : null,
        ]);
    }
}
