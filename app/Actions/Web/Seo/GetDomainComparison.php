<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Models\Catalogue\Shop;
use App\Models\Web\SeoBacklinkSummary;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\DataForSeoException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Our domain next to up to four others: backlink rank, referring domains and backlinks (3.1),
 * organic keywords, estimated traffic and the share of their keywords per intent (Labs), in the
 * shop's market. With `$fetchMissing`, a domain without data is fetched; the backlink summary of a
 * typed-in domain is fetched once a month at most.
 */
class GetDomainComparison
{
    use AsObject;

    public const int MAX_DOMAINS = 4;

    private const int BACKLINKS_FRESH_DAYS = 35;

    /**
     * @param  array<int, string>  $domains
     * @throws ValidationException
     * @throws DataForSeoException
     */
    public function handle(Shop $shop, array $domains, bool $fetchMissing): array
    {
        $ourDomain = StoreSerpResult::normaliseDomain($shop->website->domain);
        $domains   = self::domains($domains, $ourDomain);

        if (count($domains) > self::MAX_DOMAINS) {
            throw ValidationException::withMessages(['domains' => __('Compare up to :max domains at a time.', ['max' => self::MAX_DOMAINS])]);
        }

        return collect([$ourDomain, ...$domains])
            ->map(function (string $domain) use ($shop, $ourDomain, $fetchMissing) {
                $overview = FetchDomainKeywords::run($domain, $shop->country->code, $shop->language->code, $shop->website, $fetchMissing);
                $backlinks = $this->backlinkSummary($shop, $domain, $fetchMissing);

                return [
                    'domain'            => $domain,
                    'is_ours'           => $domain === $ourDomain,
                    'rank'              => $backlinks?->rank,
                    'referring_domains' => $backlinks?->referring_domains,
                    'backlinks'         => $backlinks?->backlinks,
                    'organic_keywords'  => $overview?->organic_keywords,
                    'estimated_traffic' => $overview ? (float) $overview->estimated_traffic : null,
                    'top_3'             => $overview?->top_3,
                    'top_10'            => $overview?->top_10,
                    'intents'           => $overview ? $this->intentShares($domain, $shop) : [],
                    'fetched_at'        => $overview?->date->toDateString(),
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, string>  $domains
     * @return array<int, string>
     */
    public static function domains(array $domains, string $ourDomain): array
    {
        return collect($domains)
            ->map(fn ($domain) => StoreSerpResult::normaliseDomain($domain))
            ->filter(fn ($domain) => $domain !== '' && $domain !== $ourDomain)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @throws DataForSeoException
     */
    private function backlinkSummary(Shop $shop, string $domain, bool $fetchMissing): ?SeoBacklinkSummary
    {
        $summary = SeoBacklinkSummary::where('domain', $domain)->orderByDesc('date')->first();
        $client  = DataForSeoClient::make();

        if (($summary && $summary->date->gte(today()->subDays(self::BACKLINKS_FRESH_DAYS))) || !$fetchMissing || !$client) {
            return $summary;
        }

        $result = Arr::first($client->live('backlinks/summary/live', ['target' => $domain, 'rank_scale' => 'one_hundred', 'include_subdomains' => true, 'exclude_internal_backlinks' => true], $shop->website));

        if (!$result) {
            return $summary;
        }

        return SeoBacklinkSummary::updateOrCreate(
            ['domain' => $domain, 'date' => today()],
            [
                'rank'                   => Arr::get($result, 'rank'),
                'backlinks'              => (int) Arr::get($result, 'backlinks', 0),
                'referring_domains'      => (int) Arr::get($result, 'referring_domains', 0),
                'referring_main_domains' => (int) Arr::get($result, 'referring_main_domains', 0),
                'broken_backlinks'       => (int) Arr::get($result, 'broken_backlinks', 0),
                'broken_pages'           => (int) Arr::get($result, 'broken_pages', 0),
                'spam_score'             => Arr::get($result, 'backlinks_spam_score'),
            ]
        );
    }

    /**
     * @return array<string, int> percentage of the stored keywords per intent
     */
    private function intentShares(string $domain, Shop $shop): array
    {
        $counts = DB::table('seo_domain_keywords')
            ->where('domain', $domain)
            ->where('country_code', $shop->country->code)
            ->where('language_code', strtolower($shop->language->code))
            ->whereNotNull('intent')
            ->groupBy('intent')
            ->selectRaw('intent, COUNT(*) AS keywords')
            ->pluck('keywords', 'intent');

        $total = $counts->sum();

        return $total ? $counts->map(fn ($count) => (int) round($count / $total * 100))->all() : [];
    }
}
