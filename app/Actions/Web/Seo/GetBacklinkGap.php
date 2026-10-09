<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Models\Web\Website;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\DataForSeoException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Referring domains that link to at least one of the given domains and not to our website, through
 * DataForSEO's domain intersection. A set of domains is fetched once and kept for 30 days, so the
 * team can come back to it without paying again.
 */
class GetBacklinkGap
{
    use AsObject;

    public const int MAX_DOMAINS = 4;

    private const int LIMIT = 1000;

    private const int CACHE_DAYS = 30;

    /**
     * @param  array<int, string>  $domains
     * @return array{domains: array<int, string>, fetched_at: string, rows: array<int, array{referring_domain: string, rank: int|null, linked: array<int, string>}>}
     * @throws ValidationException
     */
    public function handle(Website $website, array $domains): array
    {
        $ourDomain = StoreSerpResult::normaliseDomain($website->domain);
        $domains   = collect($domains)
            ->map(fn ($domain) => StoreSerpResult::normaliseDomain($domain))
            ->filter(fn ($domain) => $domain !== '' && $domain !== $ourDomain)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($domains === []) {
            throw ValidationException::withMessages(['domains' => __('Pick at least one competitor domain.')]);
        }

        if (count($domains) > self::MAX_DOMAINS) {
            throw ValidationException::withMessages(['domains' => __('Compare up to :max domains at a time.', ['max' => self::MAX_DOMAINS])]);
        }

        $client = DataForSeoClient::make();

        if (!$client) {
            throw ValidationException::withMessages(['domains' => __('DataForSEO is not set up. Add DATAFORSEO_LOGIN and DATAFORSEO_PASSWORD to the environment.')]);
        }

        $cacheKey = 'seo:backlink_gap:'.md5(json_encode([$ourDomain, $domains]));

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $result = Arr::first($client->live('backlinks/domain_intersection/live', [
                'targets'           => collect($domains)->mapWithKeys(fn ($domain, $index) => [(string) ($index + 1) => $domain])->all(),
                'exclude_targets'   => [$ourDomain],
                'intersection_mode' => 'partial',
                'rank_scale'        => 'one_hundred',
                'limit'             => self::LIMIT,
            ], $website)) ?? [];
        } catch (DataForSeoException $e) {
            throw ValidationException::withMessages(['domains' => $e->getMessage()]);
        }

        $gap = [
            'domains'    => $domains,
            'fetched_at' => now()->toDateString(),
            'rows'       => collect(Arr::get($result, 'items') ?? [])
                ->map(function (array $item) use ($domains) {
                    $linked = collect(Arr::get($item, 'domain_intersection') ?? [])->filter();

                    return [
                        'referring_domain' => (string) Arr::get($linked->first(), 'target'),
                        'rank'             => $linked->max('rank'),
                        'linked'           => $linked->keys()->map(fn ($key) => $domains[(int) $key - 1] ?? null)->filter()->values()->all(),
                    ];
                })
                ->filter(fn (array $row) => $row['referring_domain'] !== '')
                ->sortBy(fn (array $row) => [-count($row['linked']), -($row['rank'] ?? 0)])
                ->values()
                ->all(),
        ];

        Cache::put($cacheKey, $gap, now()->addDays(self::CACHE_DAYS));

        return $gap;
    }
}
