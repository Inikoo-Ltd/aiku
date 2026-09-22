<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Web\Website\PruneWebsitePageViews;
use App\Enums\Web\WebsiteConversionEvent\WebsiteConversionEventTypeEnum;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * How an advert page is performing, read from what the website already records: a row per page view
 * and a row per conversion. The window is the one PruneWebsitePageViews keeps, because older views
 * are deleted and a longer period would silently report on a shorter one.
 */
class GetWebpageEngagementMetrics
{
    use AsAction;

    /**
     * @return array{days: int, page_views: int, visitors: int, add_to_baskets: int, conversion_rate: float, bounces: int, bounce_rate: float, avg_time_on_page: int, timed_page_views: int}
     */
    public function handle(Webpage $webpage, ?int $days = null): array
    {
        $days = $days ?? PruneWebsitePageViews::RETENTION_DAYS;
        $from = now()->subDays($days)->toDateString();

        $views          = $this->views($webpage, $from);
        $bounces        = $this->bounces($webpage, $from);
        $addToBaskets   = $this->addToBaskets($webpage, $from);
        $pageViews      = (int)$views->page_views;
        $visitors       = (int)$views->visitors;
        $timedPageViews = (int)$views->timed_page_views;

        return [
            'days'             => $days,
            'page_views'       => $pageViews,
            'visitors'         => $visitors,
            'add_to_baskets'   => $addToBaskets,
            'conversion_rate'  => $this->rate($addToBaskets, $pageViews),
            'bounces'          => $bounces,
            'bounce_rate'      => $this->rate($bounces, $visitors),
            'avg_time_on_page' => (int)round((float)$views->avg_time_on_page),
            'timed_page_views' => $timedPageViews,
        ];
    }

    /**
     * A page view is only timed once the visitor asks for another page, so the average is taken over
     * the views that carry a duration: counting the visits that left from here as nought seconds
     * would drag the figure to nothing on the very pages an advert sends people to.
     */
    private function views(Webpage $webpage, string $from): object
    {
        $views = DB::connection('aiku_no_sticky')->table('website_page_views')
            ->where('webpage_id', $webpage->id)
            ->where('view_date', '>=', $from)
            ->selectRaw('
                COUNT(id) as page_views,
                COUNT(DISTINCT website_visitor_id) as visitors,
                COUNT(id) FILTER (WHERE duration_seconds > 0) as timed_page_views,
                COALESCE(AVG(duration_seconds) FILTER (WHERE duration_seconds > 0), 0) as avg_time_on_page
            ')
            ->first();

        return $views ?: (object)[
            'page_views'       => 0,
            'visitors'         => 0,
            'timed_page_views' => 0,
            'avg_time_on_page' => 0,
        ];
    }

    /**
     * A bounce is a session that saw this page and nothing else, which the page views themselves
     * say: a visitor row is one session, so a view whose visitor has no other view anywhere is a
     * session that went no further.
     */
    private function bounces(Webpage $webpage, string $from): int
    {
        return DB::connection('aiku_no_sticky')->table('website_page_views as page_views')
            ->where('page_views.webpage_id', $webpage->id)
            ->where('page_views.view_date', '>=', $from)
            ->whereNotExists(
                fn ($query) => $query->select(DB::raw(1))
                    ->from('website_page_views as other_views')
                    ->whereColumn('other_views.website_visitor_id', 'page_views.website_visitor_id')
                    ->whereColumn('other_views.id', '!=', 'page_views.id')
            )
            ->distinct()
            ->count('page_views.website_visitor_id');
    }

    private function addToBaskets(Webpage $webpage, string $from): int
    {
        return DB::connection('aiku_no_sticky')->table('website_conversion_events')
            ->where('webpage_id', $webpage->id)
            ->where('event_type', WebsiteConversionEventTypeEnum::ADD_TO_BASKET->value)
            ->where('event_date', '>=', $from)
            ->count();
    }

    private function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 2) : 0.0;
    }
}
