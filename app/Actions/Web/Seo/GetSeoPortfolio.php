<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Web\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * One row per live website with the SEO figures the team compares across the portfolio: site
 * health, visitors and Google Search clicks of the last 28 days against the 28 before, tracked
 * keywords in the top 10, and referring domains with their weekly change. Every figure comes from
 * data Aiku already stores; nothing here calls a provider.
 */
class GetSeoPortfolio
{
    use AsObject;

    public const int DAYS = 28;

    public function handle(): array
    {
        $websites = Website::query()
            ->where('state', WebsiteStateEnum::LIVE)
            ->with(['shop:id,slug,name,organisation_id', 'shop.organisation:id,slug'])
            ->orderBy('name')
            ->get(['id', 'name', 'domain', 'shop_id']);

        $ids = $websites->pluck('id')->all();

        $trafficTo   = today()->subDay();
        $searchTo    = ($lastSearchDay = DB::table('search_console_website_days')->max('date')) ? Carbon::parse($lastSearchDay) : null;
        $visitors    = $this->visitors($ids, $trafficTo);
        $search      = $searchTo ? $this->search($ids, $searchTo) : collect();
        $audits      = $this->audits($ids);
        $keywords    = $this->trackedKeywords($websites->pluck('shop_id')->all());
        $backlinks   = $this->backlinks($websites);

        return [
            'days'      => self::DAYS,
            'traffic'   => $this->period($trafficTo),
            'search'    => $searchTo ? $this->period($searchTo) : null,
            'websites'  => $websites->map(function (Website $website) use ($visitors, $search, $audits, $keywords, $backlinks) {
                $domain = StoreSerpResult::normaliseDomain($website->domain);

                return [
                    'id'                => $website->id,
                    'name'              => $website->name,
                    'domain'            => $website->domain,
                    'shop'              => $website->shop?->name,
                    'route'             => $website->shop ? [
                        'name'       => 'grp.org.shops.show.seo.dashboard',
                        'parameters' => [$website->shop->organisation->slug, $website->shop->slug],
                    ] : null,
                    'health'            => $audits->get($website->id)['health'] ?? null,
                    'previous_health'   => $audits->get($website->id)['previous_health'] ?? null,
                    'visitors'          => (int) ($visitors->get($website->id)->current ?? 0),
                    'previous_visitors' => (int) ($visitors->get($website->id)->previous ?? 0),
                    'clicks'            => isset($search[$website->id]) ? (int) $search[$website->id]->clicks : null,
                    'previous_clicks'   => isset($search[$website->id]) ? (int) $search[$website->id]->previous_clicks : null,
                    'position'          => isset($search[$website->id]) && $search[$website->id]->position !== null ? round((float) $search[$website->id]->position, 1) : null,
                    'tracked_keywords'  => (int) ($keywords->get($website->shop_id)->tracked ?? 0),
                    'checked_keywords'  => (int) ($keywords->get($website->shop_id)->checked ?? 0),
                    'top_10'            => (int) ($keywords->get($website->shop_id)->top_10 ?? 0),
                    'referring_domains' => $backlinks->get($domain)?->referring_domains,
                    'new_referring'     => $backlinks->get($domain)?->new_referring_domains,
                    'lost_referring'    => $backlinks->get($domain)?->lost_referring_domains,
                    'rank'              => $backlinks->get($domain)?->rank,
                ];
            })->all(),
        ];
    }

    /**
     * @return array{from: string, to: string, previous_from: string, previous_to: string}
     */
    private function period(Carbon $to): array
    {
        $from = $to->copy()->subDays(self::DAYS - 1);

        return [
            'from'          => $from->toDateString(),
            'to'            => $to->toDateString(),
            'previous_from' => $from->copy()->subDays(self::DAYS)->toDateString(),
            'previous_to'   => $from->copy()->subDay()->toDateString(),
        ];
    }

    private function visitors(array $websiteIds, Carbon $to): Collection
    {
        $period = $this->period($to);

        return DB::connection('aiku_no_sticky')->table('website_time_series_records')
            ->join('website_time_series', 'website_time_series.id', '=', 'website_time_series_records.website_time_series_id')
            ->whereIn('website_time_series.website_id', $websiteIds)
            ->where('website_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where('website_time_series_records.frequency', TimeSeriesFrequencyEnum::DAILY->singleLetter())
            ->whereBetween('website_time_series_records.period', [$period['previous_from'], $period['to']])
            ->groupBy('website_time_series.website_id')
            ->select('website_time_series.website_id')
            ->selectRaw('SUM(website_time_series_records.visitors) FILTER (WHERE website_time_series_records.period >= ?) AS current', [$period['from']])
            ->selectRaw('SUM(website_time_series_records.visitors) FILTER (WHERE website_time_series_records.period < ?) AS previous', [$period['from']])
            ->get()
            ->keyBy('website_id');
    }

    private function search(array $websiteIds, Carbon $to): Collection
    {
        $period = $this->period($to);

        return DB::connection('aiku_no_sticky')->table('search_console_website_days')
            ->whereIn('website_id', $websiteIds)
            ->whereBetween('date', [$period['previous_from'], $period['to']])
            ->groupBy('website_id')
            ->select('website_id')
            ->selectRaw('SUM(clicks) FILTER (WHERE date >= ?) AS clicks', [$period['from']])
            ->selectRaw('SUM(clicks) FILTER (WHERE date < ?) AS previous_clicks', [$period['from']])
            ->selectRaw('SUM(position * impressions) FILTER (WHERE date >= ?) / NULLIF(SUM(impressions) FILTER (WHERE date >= ?), 0) AS position', [$period['from'], $period['from']])
            ->get()
            ->keyBy('website_id');
    }

    /**
     * @return Collection<int, array{health: float|null, previous_health: float|null}>
     */
    private function audits(array $websiteIds): Collection
    {
        return DB::table('crawls')
            ->whereIn('website_id', $websiteIds)
            ->where('type', CrawlTypeEnum::AUDIT->value)
            ->whereNotNull('health_score')
            ->orderByDesc('id')
            ->get(['website_id', 'health_score'])
            ->groupBy('website_id')
            ->map(fn (Collection $audits) => [
                'health'          => round((float) $audits->first()->health_score, 1),
                'previous_health' => $audits->count() > 1 ? round((float) $audits->get(1)->health_score, 1) : null,
            ]);
    }

    private function trackedKeywords(array $shopIds): Collection
    {
        return DB::table('seo_tracked_keywords')
            ->whereIn('shop_id', $shopIds)
            ->where('is_active', true)
            ->groupBy('shop_id')
            ->select('shop_id')
            ->selectRaw('COUNT(*) AS tracked')
            ->selectRaw('COUNT(last_checked_at) AS checked')
            ->selectRaw('COUNT(*) FILTER (WHERE position <= 10) AS top_10')
            ->get()
            ->keyBy('shop_id');
    }

    private function backlinks(Collection $websites): Collection
    {
        $domains = $websites->map(fn (Website $website) => StoreSerpResult::normaliseDomain($website->domain))->filter()->values()->all();

        return DB::table('seo_backlink_summaries')
            ->whereIn('domain', $domains)
            ->orderByDesc('date')
            ->get(['domain', 'rank', 'referring_domains', 'new_referring_domains', 'lost_referring_domains'])
            ->unique('domain')
            ->keyBy('domain');
    }
}
