<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Models\Catalogue\Shop;
use App\Models\Web\SeoBacklinkSummary;
use App\Models\Web\SeoDomainTraffic;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\DataForSeoException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Our domain next to up to four others: backlink rank, referring domains and backlinks (3.1),
 * organic keywords, estimated traffic and the share of their keywords per intent (Labs), and the
 * monthly search traffic with its history (3.5), in the shop's market. With `$fetchMissing`, a
 * domain without data is fetched; the backlink summary and the traffic history of a typed-in domain
 * are fetched once a month at most. A traffic fetch that fails leaves the rest of the comparison.
 */
class GetDomainComparison
{
    use AsObject;

    public const int MAX_DOMAINS = 4;

    private const int BACKLINKS_FRESH_DAYS = 35;

    public const int TRAFFIC_MONTHS = 36;

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

        $allDomains = [$ourDomain, ...$domains];

        if ($fetchMissing) {
            try {
                FetchDomainTraffic::run($allDomains, $shop->country->code, $shop->language->code, $shop->website);
            } catch (DataForSeoException|ValidationException) {
            }
        }

        $traffic = $this->traffic($shop, $allDomains);

        return collect($allDomains)
            ->map(function (string $domain) use ($shop, $ourDomain, $fetchMissing, $traffic) {
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
                    ...$this->trafficFigures($traffic->get($domain, collect())),
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
     * @param  array<int, string>  $domains
     * @return Collection<string, Collection<int, SeoDomainTraffic>>
     */
    private function traffic(Shop $shop, array $domains): Collection
    {
        return SeoDomainTraffic::query()
            ->whereIn('domain', $domains)
            ->where('country_code', $shop->country->code)
            ->where('language_code', strtolower($shop->language->code))
            ->where('month', '>=', today()->startOfMonth()->subMonths(self::TRAFFIC_MONTHS + 1))
            ->orderBy('month')
            ->get()
            ->groupBy('domain');
    }

    /**
     * The latest month with data, the same month a year before, and the organic history.
     *
     * @param  Collection<int, SeoDomainTraffic>  $months
     */
    private function trafficFigures(Collection $months): array
    {
        $latest   = $months->last(fn (SeoDomainTraffic $month) => $month->organic_keywords > 0 || $month->paid_keywords > 0);
        $yearAgo  = $latest ? $months->first(fn (SeoDomainTraffic $month) => $month->month->eq($latest->month->copy()->subYear())) : null;

        return [
            'search_traffic'          => $latest ? (float) $latest->organic_traffic : null,
            'search_traffic_year_ago' => $yearAgo ? (float) $yearAgo->organic_traffic : null,
            'paid_traffic'            => $latest ? (float) $latest->paid_traffic : null,
            'traffic_month'           => $latest?->month->toDateString(),
            'traffic_fetched_at'      => $months->max('updated_at')?->toDateString(),
            'traffic_history'         => $months
                ->filter(fn (SeoDomainTraffic $month) => !$latest || $month->month->lte($latest->month))
                ->take(-self::TRAFFIC_MONTHS)
                ->mapWithKeys(fn (SeoDomainTraffic $month) => [$month->month->format('Y-m') => round((float) $month->organic_traffic)])
                ->all(),
        ];
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
