<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Enums\Web\Seo\SeoContentSuggestionStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Web\SeoBacklinkSummary;
use App\Models\Web\SeoContentSuggestion;
use App\Models\Web\SeoTrackedKeyword;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The week of one shop in a few lines for the weekly SEO email: traffic and Google clicks against
 * the week before, keyword winners and losers, site health, referring domains, new missing pages and
 * the suggested fixes waiting. Each section only appears when it has data.
 */
class GetSeoWeeklyReport
{
    use AsObject;

    public const int DAYS = 7;

    private const int MOVERS = 5;

    /**
     * @return array<int, array{title: string, lines: array<int, string>}>
     */
    public function handle(Shop $shop): array
    {
        $website = $shop->website;

        if (!$website) {
            return [];
        }

        return array_values(array_filter([
            $this->traffic($website->id),
            $this->search($website->id),
            $this->rankings($shop),
            $this->audit($website->id),
            $this->backlinks($website->domain),
            $this->missingPages($website->id),
            $this->suggestions($website->id),
        ]));
    }

    public static function change(float|int|null $current, float|int|null $previous): string
    {
        if ($current === null || $previous === null || (float) $previous === 0.0) {
            return '';
        }

        $percent = round(($current - $previous) / $previous * 100, 1);

        return ' ('.($percent > 0 ? '+' : '').$percent.'%)';
    }

    private function traffic(int $websiteId): ?array
    {
        $to   = today()->subDay();
        $from = $to->copy()->subDays(self::DAYS - 1);

        $visitors = DB::connection('aiku_no_sticky')->table('website_time_series_records')
            ->join('website_time_series', 'website_time_series.id', '=', 'website_time_series_records.website_time_series_id')
            ->where('website_time_series.website_id', $websiteId)
            ->where('website_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where('website_time_series_records.frequency', TimeSeriesFrequencyEnum::DAILY->singleLetter())
            ->whereBetween('website_time_series_records.period', [$from->copy()->subDays(self::DAYS)->toDateString(), $to->toDateString()])
            ->selectRaw('SUM(visitors) FILTER (WHERE period >= ?) AS current', [$from->toDateString()])
            ->selectRaw('SUM(visitors) FILTER (WHERE period < ?) AS previous', [$from->toDateString()])
            ->first();

        if (!$visitors?->current) {
            return null;
        }

        return [
            'title' => __('Visitors'),
            'lines' => [__(':visitors visitors from :from to :to', ['visitors' => number_format((int) $visitors->current), 'from' => $from->format('j M'), 'to' => $to->format('j M')]).self::change((int) $visitors->current, (int) $visitors->previous)],
        ];
    }

    private function search(int $websiteId): ?array
    {
        $lastDay = DB::table('search_console_website_days')->where('website_id', $websiteId)->max('date');

        if (!$lastDay) {
            return null;
        }

        $to   = Carbon::parse($lastDay);
        $from = $to->copy()->subDays(self::DAYS - 1);

        $search = DB::connection('aiku_no_sticky')->table('search_console_website_days')
            ->where('website_id', $websiteId)
            ->whereBetween('date', [$from->copy()->subDays(self::DAYS)->toDateString(), $to->toDateString()])
            ->selectRaw('SUM(clicks) FILTER (WHERE date >= ?) AS clicks', [$from->toDateString()])
            ->selectRaw('SUM(clicks) FILTER (WHERE date < ?) AS previous_clicks', [$from->toDateString()])
            ->selectRaw('SUM(impressions) FILTER (WHERE date >= ?) AS impressions', [$from->toDateString()])
            ->selectRaw('SUM(impressions) FILTER (WHERE date < ?) AS previous_impressions', [$from->toDateString()])
            ->selectRaw('SUM(position * impressions) FILTER (WHERE date >= ?) / NULLIF(SUM(impressions) FILTER (WHERE date >= ?), 0) AS position', [$from->toDateString(), $from->toDateString()])
            ->first();

        return [
            'title' => __('Google Search'),
            'lines' => [
                __(':clicks clicks from :from to :to', ['clicks' => number_format((int) $search->clicks), 'from' => $from->format('j M'), 'to' => $to->format('j M')]).self::change((int) $search->clicks, (int) $search->previous_clicks),
                __(':impressions impressions', ['impressions' => number_format((int) $search->impressions)]).self::change((int) $search->impressions, (int) $search->previous_impressions),
                $search->position !== null ? __('Average position :position', ['position' => round((float) $search->position, 1)]) : null,
            ],
        ];
    }

    private function rankings(Shop $shop): ?array
    {
        $checked = SeoTrackedKeyword::where('shop_id', $shop->id)->where('is_active', true)->whereNotNull('last_checked_at');

        if (!(clone $checked)->exists()) {
            return null;
        }

        $recent = (clone $checked)->where('last_checked_at', '>=', today()->subDays(self::DAYS))->whereNotNull('previous_checked_at');

        $winners = (clone $recent)->whereNotNull('position')
            ->whereRaw('(previous_position IS NULL OR position < previous_position)')
            ->orderByRaw('COALESCE(previous_position, 101) - position DESC')
            ->limit(self::MOVERS)
            ->get(['keyword', 'position', 'previous_position']);

        $losers = (clone $recent)->whereNotNull('previous_position')
            ->whereRaw('(position IS NULL OR position > previous_position)')
            ->orderByRaw('COALESCE(position, 101) - previous_position DESC')
            ->limit(self::MOVERS)
            ->get(['keyword', 'position', 'previous_position']);

        $move = fn (SeoTrackedKeyword $keyword) => __(':keyword: :previous to :position', [
            'keyword'  => $keyword->keyword,
            'previous' => $keyword->previous_position ?? __('not ranked'),
            'position' => $keyword->position ?? __('not ranked'),
        ]);

        return [
            'title' => __('Tracked keywords'),
            'lines' => [
                __(':top of :checked checked keywords are in the top 10', ['top' => (clone $checked)->where('position', '<=', 10)->count(), 'checked' => (clone $checked)->count()]),
                ...$winners->map(fn ($keyword) => __('Up').' '.$move($keyword))->all(),
                ...$losers->map(fn ($keyword) => __('Down').' '.$move($keyword))->all(),
            ],
        ];
    }

    private function audit(int $websiteId): ?array
    {
        $audits = DB::table('crawls')
            ->where('website_id', $websiteId)
            ->where('type', CrawlTypeEnum::AUDIT->value)
            ->whereNotNull('health_score')
            ->orderByDesc('id')
            ->limit(2)
            ->get(['health_score', 'end_at']);

        if ($audits->isEmpty()) {
            return null;
        }

        $latest   = $audits->first();
        $previous = $audits->get(1);

        return [
            'title' => __('Site audit'),
            'lines' => [__('Site health :health%', ['health' => round((float) $latest->health_score, 1)]).($previous ? ' ('.sprintf('%+.1f', $latest->health_score - $previous->health_score).' '.__('points').')' : '')],
        ];
    }

    private function backlinks(string $domain): ?array
    {
        $summary = SeoBacklinkSummary::where('domain', StoreSerpResult::normaliseDomain($domain))->orderByDesc('date')->first();

        if (!$summary) {
            return null;
        }

        return [
            'title' => __('Backlinks'),
            'lines' => [
                __(':count referring domains, rank :rank', ['count' => number_format($summary->referring_domains), 'rank' => $summary->rank ?? '-']),
                $summary->new_referring_domains !== null ? __(':new new and :lost lost since the week before', ['new' => $summary->new_referring_domains, 'lost' => $summary->lost_referring_domains ?? 0]) : null,
            ],
        ];
    }

    private function missingPages(int $websiteId): ?array
    {
        $paths = DB::table('website_not_found_paths')
            ->where('website_id', $websiteId)
            ->where('is_ignored', false)
            ->where('first_seen_at', '>=', today()->subDays(self::DAYS))
            ->orderByDesc('hits')
            ->get(['path', 'hits']);

        if ($paths->isEmpty()) {
            return null;
        }

        return [
            'title' => __('Missing pages'),
            'lines' => [
                trans_choice('{1} 1 new path answered 404|[2,*] :count new paths answered 404', $paths->count()),
                ...$paths->take(3)->map(fn ($path) => __(':path, :hits hits', ['path' => $path->path, 'hits' => $path->hits]))->all(),
            ],
        ];
    }

    private function suggestions(int $websiteId): ?array
    {
        $pending = SeoContentSuggestion::where('website_id', $websiteId)->where('state', SeoContentSuggestionStateEnum::PENDING)->count();

        return $pending ? [
            'title' => __('Suggested fixes'),
            'lines' => [trans_choice('{1} 1 suggested title or description waits for someone to use or dismiss it|[2,*] :count suggested titles and descriptions wait for someone to use or dismiss them', $pending)],
        ] : null;
    }
}
