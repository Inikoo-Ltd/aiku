<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl;

use App\Actions\Web\WebVital\GetWebVitalsReport;
use App\Models\Web\Crawl;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Adds what our own data knows about each crawled page to its facts, before the issues are checked:
 * how fast real visitors found it (75th percentile of the web vitals they sent), how it did in
 * Google search (Search Console clicks, impressions and position) and how often it was visited
 * (our own page views), all over the last 28 days. A
 * website with no such data in that window gets none, so no page is flagged for missing it.
 */
class EnrichCrawlPages
{
    use AsObject;

    public const int DAYS = 28;

    public function handle(Crawl $crawl): void
    {
        $from = now()->subDays(self::DAYS)->startOfDay();

        if (DB::table('web_vital_samples')->where('website_id', $crawl->website_id)->where('created_at', '>=', $from)->exists()) {
            $this->addVitals($crawl, $from);
        }

        if (DB::table('search_console_page_days')->where('website_id', $crawl->website_id)->where('date', '>=', $from->toDateString())->exists()) {
            $this->addSearch($crawl, $from);
        }

        if (DB::table('website_page_views')->where('website_id', $crawl->website_id)->where('view_date', '>=', $from->toDateString())->exists()) {
            $this->addVisits($crawl, $from);
        }
    }

    /**
     * Page views and distinct visitors from our own website analytics, which stand in for Google
     * Analytics: every page that answered 200 gets a count, zero included.
     */
    private function addVisits(Crawl $crawl, $from): void
    {
        $visits = DB::table('website_page_views')
            ->where('website_id', $crawl->website_id)
            ->whereNotNull('webpage_id')
            ->where('view_date', '>=', $from->toDateString())
            ->groupBy('webpage_id')
            ->select('webpage_id')
            ->selectRaw('COUNT(*) as views')
            ->selectRaw('COUNT(DISTINCT website_visitor_id) as visitors');

        DB::update(
            "UPDATE crawl_pages SET facts = coalesce(crawl_pages.facts, '{}'::jsonb) || jsonb_build_object('visits', jsonb_build_object(
                'views', coalesce(visits.views, 0),
                'visitors', coalesce(visits.visitors, 0)
            ))
            FROM crawl_pages AS pages LEFT JOIN (".$visits->toSql().') AS visits ON visits.webpage_id = pages.webpage_id
            WHERE crawl_pages.id = pages.id AND crawl_pages.crawl_id = ? AND crawl_pages.webpage_id IS NOT NULL AND crawl_pages.status_code = 200',
            [...$visits->getBindings(), $crawl->id]
        );
    }

    private function addVitals(Crawl $crawl, $from): void
    {
        $samples = DB::table('web_vital_samples')
            ->where('website_id', $crawl->website_id)
            ->whereNotNull('webpage_id')
            ->where('created_at', '>=', $from)
            ->groupBy('webpage_id')
            ->havingRaw('COUNT(*) >= ?', [GetWebVitalsReport::MIN_SAMPLES])
            ->select('webpage_id')
            ->selectRaw('COUNT(*) as samples')
            ->selectRaw('percentile_cont(0.75) within group (order by lcp) as lcp')
            ->selectRaw('percentile_cont(0.75) within group (order by inp) as inp')
            ->selectRaw('percentile_cont(0.75) within group (order by cls) as cls');

        DB::update(
            "UPDATE crawl_pages SET facts = coalesce(crawl_pages.facts, '{}'::jsonb) || jsonb_build_object('vitals', jsonb_build_object(
                'samples', vitals.samples,
                'lcp', round(vitals.lcp::numeric),
                'inp', round(vitals.inp::numeric),
                'cls', round(vitals.cls::numeric, 3)
            ))
            FROM (".$samples->toSql().') AS vitals
            WHERE crawl_pages.crawl_id = ? AND crawl_pages.webpage_id = vitals.webpage_id AND crawl_pages.status_code = 200',
            [...$samples->getBindings(), $crawl->id]
        );
    }

    private function addSearch(Crawl $crawl, $from): void
    {
        $search = DB::table('search_console_page_days')
            ->where('website_id', $crawl->website_id)
            ->whereNotNull('webpage_id')
            ->where('date', '>=', $from->toDateString())
            ->groupBy('webpage_id')
            ->select('webpage_id')
            ->selectRaw('SUM(clicks) as clicks')
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('ROUND(SUM(position * impressions) / NULLIF(SUM(impressions), 0), 1) as position');

        DB::update(
            "UPDATE crawl_pages SET facts = coalesce(crawl_pages.facts, '{}'::jsonb) || jsonb_build_object('search', jsonb_build_object(
                'clicks', coalesce(search.clicks, 0),
                'impressions', coalesce(search.impressions, 0),
                'position', search.position
            ))
            FROM crawl_pages AS pages LEFT JOIN (".$search->toSql().') AS search ON search.webpage_id = pages.webpage_id
            WHERE crawl_pages.id = pages.id AND crawl_pages.crawl_id = ? AND crawl_pages.webpage_id IS NOT NULL AND crawl_pages.status_code = 200',
            [...$search->getBindings(), $crawl->id]
        );
    }
}
