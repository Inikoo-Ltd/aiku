<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Models\Catalogue\Shop;
use App\Models\Web\Website;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\DataForSeoException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The domains that rank for the most of our keywords in the shop's market, from DataForSEO Labs,
 * kept for 30 days. Our own websites and the competitors already set are left out.
 */
class GetOrganicCompetitors
{
    use AsObject;

    private const int LIMIT = 30;

    private const int CACHE_DAYS = 30;

    /**
     * @return array<int, array{domain: string, shared_keywords: int, average_position: float|null, organic_keywords: int, estimated_traffic: float}>
     * @throws ValidationException
     * @throws DataForSeoException
     */
    public function handle(Shop $shop): array
    {
        $website = $shop->website;
        $client  = DataForSeoClient::make();

        if (!$website || !$client || !$shop->country || !$shop->language) {
            return [];
        }

        $domain     = StoreSerpResult::normaliseDomain($website->domain);
        $ownDomains = Website::pluck('domain')->map(fn ($domain) => StoreSerpResult::normaliseDomain($domain))->all();
        $known      = $shop->seoCompetitors->map(fn ($competitor) => StoreSerpResult::normaliseDomain($competitor->domain))->all();

        $suggestions = Cache::remember(
            "seo:organic_competitors:$domain:{$shop->country->code}:{$shop->language->code}",
            now()->addDays(self::CACHE_DAYS),
            function () use ($client, $domain, $shop, $website) {
                [$locationCode, $languageCode] = GetKeywordIdeas::make()->location($client, $shop->country->code, $shop->language->code);

                return collect(Arr::get(Arr::first($client->live('dataforseo_labs/google/competitors_domain/live', [
                    'target'        => $domain,
                    'location_code' => $locationCode,
                    'language_code' => $languageCode,
                    'limit'         => self::LIMIT,
                ], $website)) ?? [], 'items') ?? [])
                    ->map(fn (array $item) => [
                        'domain'            => StoreSerpResult::normaliseDomain(Arr::get($item, 'domain')),
                        'shared_keywords'   => (int) Arr::get($item, 'intersections', 0),
                        'average_position'  => Arr::get($item, 'avg_position') !== null ? round((float) Arr::get($item, 'avg_position'), 1) : null,
                        'organic_keywords'  => (int) Arr::get($item, 'full_domain_metrics.organic.count', 0),
                        'estimated_traffic' => round((float) Arr::get($item, 'full_domain_metrics.organic.etv', 0)),
                    ])
                    ->all();
            }
        );

        return collect($suggestions)
            ->reject(fn (array $suggestion) => in_array($suggestion['domain'], [...$ownDomains, ...$known], true))
            ->values()
            ->all();
    }
}
