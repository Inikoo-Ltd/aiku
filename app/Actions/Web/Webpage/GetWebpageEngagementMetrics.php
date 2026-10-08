<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Web\WebsitePageView\GetWebsiteEntryPageViews;
use App\Enums\Web\WebsiteConversionEvent\WebsiteConversionEventTypeEnum;
use App\Models\Web\Webpage;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * How an advert page is performing, read from what the website already records: a row per page view
 * and a row per conversion, over the last DEFAULT_DAYS days. It must stay within what
 * PruneWebsitePageViews keeps, because older views are deleted and a longer period would silently
 * report on a shorter one.
 *
 * The totals are added up from the same daily rows the chart is drawn from, so a number in a tile
 * and the line above it can never disagree. A session that crosses midnight is therefore counted as
 * a visitor on both days.
 */
class GetWebpageEngagementMetrics
{
    use AsAction;

    public const int DEFAULT_DAYS = 30;

    /**
     * @return array{days: int, page_views: int, visitors: int, add_to_baskets: int, entrances: int, checkouts: int, purchases: int, conversion_rate: float, bounces: int, bounce_rate: float, avg_time_on_page: int, timed_page_views: int, history: array<int, array{date: string, page_views: int, visitors: int, bounces: int, add_to_baskets: int, entrances: int, checkouts: int, purchases: int, conversion_rate: ?float, bounce_rate: ?float, avg_time_on_page: ?int}>}
     */
    public function handle(Webpage $webpage, ?int $days = null): array
    {
        $days ??= self::DEFAULT_DAYS;
        $until = now()->startOfDay();
        $from  = $until->copy()->subDays($days - 1);

        $views        = $this->dailyViews($webpage, $from);
        $bounces      = $this->dailyBounces($webpage, $from);
        $addToBaskets = $this->dailyAddToBaskets($webpage, $from);
        $entrances    = $this->dailyEntrances($webpage, $from);
        $landings     = $this->dailyLandingConversions($webpage, $from);

        $history = [];
        $totals  = [
            'page_views'       => 0,
            'visitors'         => 0,
            'timed_page_views' => 0,
            'timed_duration'   => 0,
            'bounces'          => 0,
            'add_to_baskets'   => 0,
            'entrances'        => 0,
            'checkouts'        => 0,
            'purchases'        => 0,
        ];

        for ($date = $from->copy(); $date <= $until; $date->addDay()) {
            $day = $date->toDateString();

            $dayViews          = $views->get($day);
            $dayPageViews      = (int)($dayViews->page_views ?? 0);
            $dayVisitors       = (int)($dayViews->visitors ?? 0);
            $dayTimedPageViews = (int)($dayViews->timed_page_views ?? 0);
            $dayTimedDuration  = (int)($dayViews->timed_duration ?? 0);
            $dayBounces        = (int)($bounces->get($day)?->bounces ?? 0);
            $dayAddToBaskets   = (int)($addToBaskets->get($day)?->add_to_baskets ?? 0);
            $dayEntrances      = (int)($entrances->get($day)?->entrances ?? 0);
            $dayCheckouts      = (int)($landings->get($day)?->checkouts ?? 0);
            $dayPurchases      = (int)($landings->get($day)?->purchases ?? 0);

            $totals['page_views']       += $dayPageViews;
            $totals['visitors']         += $dayVisitors;
            $totals['timed_page_views'] += $dayTimedPageViews;
            $totals['timed_duration']   += $dayTimedDuration;
            $totals['bounces']          += $dayBounces;
            $totals['add_to_baskets']   += $dayAddToBaskets;
            $totals['entrances']        += $dayEntrances;
            $totals['checkouts']        += $dayCheckouts;
            $totals['purchases']        += $dayPurchases;

            $history[] = [
                'date'             => $day,
                'page_views'       => $dayPageViews,
                'visitors'         => $dayVisitors,
                'bounces'          => $dayBounces,
                'add_to_baskets'   => $dayAddToBaskets,
                'entrances'        => $dayEntrances,
                'checkouts'        => $dayCheckouts,
                'purchases'        => $dayPurchases,
                'conversion_rate'  => $dayEntrances > 0 ? $this->rate($dayPurchases, $dayEntrances) : null,
                'bounce_rate'      => $dayVisitors > 0 ? $this->rate($dayBounces, $dayVisitors) : null,
                'avg_time_on_page' => $dayTimedPageViews > 0 ? (int)round($dayTimedDuration / $dayTimedPageViews) : null,
            ];
        }

        return [
            'days'             => $days,
            'page_views'       => $totals['page_views'],
            'visitors'         => $totals['visitors'],
            'add_to_baskets'   => $totals['add_to_baskets'],
            'entrances'        => $totals['entrances'],
            'checkouts'        => $totals['checkouts'],
            'purchases'        => $totals['purchases'],
            'conversion_rate'  => $this->rate($totals['purchases'], $totals['entrances']),
            'bounces'          => $totals['bounces'],
            'bounce_rate'      => $this->rate($totals['bounces'], $totals['visitors']),
            'avg_time_on_page' => $totals['timed_page_views'] > 0
                ? (int)round($totals['timed_duration'] / $totals['timed_page_views'])
                : 0,
            'timed_page_views' => $totals['timed_page_views'],
            'history'          => $history,
        ];
    }

    /**
     * A page view is only timed once the visitor asks for another page, so the time on page is taken
     * over the views that carry a duration: counting the visits that left from here as nought
     * seconds would drag the figure to nothing on the very pages an advert sends people to.
     */
    private function dailyViews(Webpage $webpage, CarbonInterface $from): Collection
    {
        return $this->keyByDate(
            DB::connection('aiku_no_sticky')->table('website_page_views')
                ->where('webpage_id', $webpage->id)
                ->where('view_date', '>=', $from->toDateString())
                ->selectRaw('
                    view_date as date,
                    COUNT(id) as page_views,
                    COUNT(DISTINCT website_visitor_id) as visitors,
                    COUNT(id) FILTER (WHERE duration_seconds > 0) as timed_page_views,
                    COALESCE(SUM(duration_seconds) FILTER (WHERE duration_seconds > 0), 0) as timed_duration
                ')
                ->groupBy('view_date')
                ->get()
        );
    }

    /**
     * A bounce is a session that saw this page and nothing else, which the page views themselves
     * say: a visitor row is one session, so a view whose visitor has no other view anywhere is a
     * session that went no further.
     */
    private function dailyBounces(Webpage $webpage, CarbonInterface $from): Collection
    {
        return $this->keyByDate(
            DB::connection('aiku_no_sticky')->table('website_page_views as page_views')
                ->where('page_views.webpage_id', $webpage->id)
                ->where('page_views.view_date', '>=', $from->toDateString())
                ->whereNotExists(
                    fn ($query) => $query->select(DB::raw(1))
                        ->from('website_page_views as other_views')
                        ->whereColumn('other_views.website_visitor_id', 'page_views.website_visitor_id')
                        ->whereColumn('other_views.id', '!=', 'page_views.id')
                )
                ->selectRaw('
                    page_views.view_date as date,
                    COUNT(DISTINCT page_views.website_visitor_id) as bounces
                ')
                ->groupBy('page_views.view_date')
                ->get()
        );
    }

    private function dailyAddToBaskets(Webpage $webpage, CarbonInterface $from): Collection
    {
        return $this->keyByDate(
            DB::connection('aiku_no_sticky')->table('website_conversion_events')
                ->where('webpage_id', $webpage->id)
                ->where('event_type', WebsiteConversionEventTypeEnum::ADD_TO_BASKET->value)
                ->where('event_date', '>=', $from->toDateString())
                ->selectRaw('
                    event_date as date,
                    COUNT(id) as add_to_baskets
                ')
                ->groupBy('event_date')
                ->get()
        );
    }

    private function dailyEntrances(Webpage $webpage, CarbonInterface $from): Collection
    {
        return $this->keyByDate(
            GetWebsiteEntryPageViews::run()
                ->where('entry_views.webpage_id', $webpage->id)
                ->where('entry_views.view_date', '>=', $from->toDateString())
                ->selectRaw('
                    entry_views.view_date as date,
                    COUNT(DISTINCT entry_visitors.visitor_hash) as entrances
                ')
                ->groupBy('entry_views.view_date')
                ->get()
        );
    }

    private function dailyLandingConversions(Webpage $webpage, CarbonInterface $from): Collection
    {
        $checkout = WebsiteConversionEventTypeEnum::CHECKOUT->value;
        $purchase = WebsiteConversionEventTypeEnum::PURCHASE->value;

        return $this->keyByDate(
            DB::connection('aiku_no_sticky')->table('website_conversion_events')
                ->where('landing_webpage_id', $webpage->id)
                ->whereIn('event_type', [$checkout, $purchase])
                ->where('event_date', '>=', $from->toDateString())
                ->selectRaw("
                    event_date as date,
                    COUNT(id) FILTER (WHERE event_type = '$checkout') as checkouts,
                    COUNT(id) FILTER (WHERE event_type = '$purchase') as purchases
                ")
                ->groupBy('event_date')
                ->get()
        );
    }

    private function keyByDate(Collection $rows): Collection
    {
        return $rows->keyBy(fn (object $row) => Carbon::parse($row->date)->toDateString());
    }

    private function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 2) : 0.0;
    }
}
