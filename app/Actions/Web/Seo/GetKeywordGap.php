<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Models\Catalogue\Shop;
use App\Models\Web\SeoDomainKeyword;
use App\Services\DataForSeo\DataForSeoException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Our keywords against up to four other domains, from the top 100 positions each one has in the
 * shop's market (up to 1,000 keywords per domain, highest volume first). Every keyword is put in
 * the groups it belongs to, as Semrush's Keyword Gap does; the groups overlap (Missing is part of
 * Untapped).
 */
class GetKeywordGap
{
    use AsObject;

    public const array GROUPS = ['shared', 'missing', 'weak', 'strong', 'untapped', 'unique'];

    /**
     * @param  array<int, string>  $domains
     * @return array{domains: array<int, string>, counts: array<string, int>, rows: array<int, array>}
     * @throws ValidationException
     * @throws DataForSeoException
     */
    public function handle(Shop $shop, array $domains): array
    {
        $ourDomain = StoreSerpResult::normaliseDomain($shop->website->domain);
        $domains   = GetDomainComparison::domains($domains, $ourDomain);

        if ($domains === []) {
            throw ValidationException::withMessages(['domains' => __('Pick at least one competitor domain.')]);
        }

        if (count($domains) > GetDomainComparison::MAX_DOMAINS) {
            throw ValidationException::withMessages(['domains' => __('Compare up to :max domains at a time.', ['max' => GetDomainComparison::MAX_DOMAINS])]);
        }

        $allDomains   = [$ourDomain, ...$domains];
        $countryCode  = $shop->country->code;
        $languageCode = strtolower($shop->language->code);

        foreach ($allDomains as $domain) {
            FetchDomainKeywords::run($domain, $countryCode, $languageCode, $shop->website);
        }

        $keywords = SeoDomainKeyword::query()
            ->whereIn('domain', $allDomains)
            ->where('country_code', $countryCode)
            ->where('language_code', $languageCode)
            ->get(['domain', 'keyword', 'position', 'url', 'search_volume', 'keyword_difficulty', 'intent'])
            ->groupBy('keyword');

        $rows = $keywords->map(function ($rankings, string $keyword) use ($allDomains, $ourDomain, $domains) {
            $positions = collect($allDomains)->mapWithKeys(fn ($domain) => [$domain => $rankings->firstWhere('domain', $domain)?->position])->all();
            $first     = $rankings->sortByDesc('search_volume')->first();

            return [
                'keyword'            => $keyword,
                'intent'             => $first->intent,
                'search_volume'      => $first->search_volume,
                'keyword_difficulty' => $first->keyword_difficulty,
                'positions'          => $positions,
                'our_url'            => $rankings->firstWhere('domain', $ourDomain)?->url,
                'groups'             => self::groups($positions[$ourDomain], array_map(fn ($domain) => $positions[$domain], $domains)),
            ];
        })->values();

        return [
            'domains' => $allDomains,
            'counts'  => collect(self::GROUPS)->mapWithKeys(fn ($group) => [$group => $rows->filter(fn ($row) => in_array($group, $row['groups'], true))->count()])->all(),
            'rows'    => $rows->sortByDesc('search_volume')->values()->all(),
        ];
    }

    /**
     * @param  array<int, int|null>  $theirs
     * @return array<int, string>
     */
    public static function groups(?int $ours, array $theirs): array
    {
        $ranking = array_values(array_filter($theirs, fn ($position) => $position !== null));
        $groups  = [];

        if ($ours !== null && count($ranking) === count($theirs)) {
            $groups[] = 'shared';
        }

        if ($ours === null && count($ranking) === count($theirs)) {
            $groups[] = 'missing';
        }

        if ($ours !== null && count($ranking) === count($theirs) && $ours > max($ranking)) {
            $groups[] = 'weak';
        }

        if ($ours !== null && $ranking !== [] && $ours < min($ranking)) {
            $groups[] = 'strong';
        }

        if ($ours === null && $ranking !== []) {
            $groups[] = 'untapped';
        }

        if ($ours !== null && $ranking === []) {
            $groups[] = 'unique';
        }

        return $groups;
    }
}
