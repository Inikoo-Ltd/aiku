<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Enums\Search\WebsiteSearchSourceEnum;
use App\Models\Helpers\WebsiteSearchLog;
use App\Models\Web\Website;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteSearchAnalytics
{
    use AsObject;

    public function handle(Website $website, int $days = 30): array
    {
        $base = WebsiteSearchLog::where('website_search_logs.website_id', $website->id)
            ->where('website_search_logs.created_at', '>=', now()->subDays($days));

        $totalSearches   = (clone $base)->count();
        $clicked         = (clone $base)->whereNotNull('clicked_at')->count();
        $zeroResults     = (clone $base)->where('keyword_results_count', 0)->count();
        $loggedInSearches = (clone $base)->whereNotNull('web_user_id')->count();

        $topQueries = $this->groupedQueries(clone $base)
            ->orderByDesc('searches')
            ->orderByDesc('clicks')
            ->orderBy('query')
            ->limit(10)
            ->get();

        $topZeroQueries = $this->groupedQueries((clone $base)->where('keyword_results_count', 0))
            ->orderByDesc('searches')
            ->orderBy('query')
            ->limit(10)
            ->get();

        $topAbandonedQueries = $this->groupedQueries(
            (clone $base)->where('results_count', '>', 0)->whereNull('clicked_at')
        )
            ->orderByDesc('searches')
            ->orderBy('query')
            ->limit(10)
            ->get();

        $topCustomers = (clone $base)
            ->join('customers', 'customers.id', '=', 'website_search_logs.customer_id')
            ->selectRaw('customers.name as username, customers.slug as customer_slug, count(*) as searches, count(website_search_logs.clicked_at) as clicks')
            ->groupBy('customers.name', 'customers.slug')
            ->orderByDesc('searches')
            ->orderBy('customers.name')
            ->limit(10)
            ->get();

        $topClickedPages = (clone $base)
            ->whereNotNull('clicked_url')
            ->selectRaw('clicked_url, count(*) as clicks')
            ->groupBy('clicked_url')
            ->orderByDesc('clicks')
            ->orderBy('clicked_url')
            ->limit(10)
            ->get();

        $devices = (clone $base)
            ->whereNotNull('device')
            ->selectRaw('device, count(*) as searches, count(clicked_at) as clicks')
            ->groupBy('device')
            ->orderByDesc('searches')
            ->limit(10)
            ->get();

        return [
            'sources'            => $this->sources(clone $base),
            'days'               => $days,
            'total_searches'     => $totalSearches,
            'logged_in_searches' => $loggedInSearches,
            'guest_searches'     => $totalSearches - $loggedInSearches,
            'click_through'      => $totalSearches ? round($clicked / $totalSearches * 100, 1) : 0,
            'zero_results_rate'  => $totalSearches ? round($zeroResults / $totalSearches * 100, 1) : 0,
            'top_queries'           => $topQueries,
            'top_zero_queries'      => $topZeroQueries,
            'top_abandoned_queries' => $topAbandonedQueries,
            'top_searchers'         => $topCustomers,
            'top_clicked_pages' => $topClickedPages,
            'devices'           => $devices,
            'trend'             => GetWebsiteSearchTrend::run($website, $days),
        ];
    }

    /**
     * Which control visitors reach for to start a search. The share is measured against the
     * searches that carry a source rather than against every search, so that logs predating
     * the tracking do not dilute the percentages.
     *
     * @return array<int, array{source: string, label: string, searches: int, clicks: int, share: float}>
     */
    protected function sources(Builder $query): array
    {
        $rows = $query
            ->whereNotNull('source')
            ->selectRaw('source, count(*) as searches, count(clicked_at) as clicks')
            ->groupBy('source')
            ->orderByDesc('searches')
            ->orderBy('source')
            ->get();

        $sourcedSearches = $rows->sum('searches');
        $labels          = WebsiteSearchSourceEnum::labels();

        return $rows->map(fn ($row) => [
            'source'   => $row->source,
            'label'    => Arr::get($labels, $row->source, $row->source),
            'searches' => (int)$row->searches,
            'clicks'   => (int)$row->clicks,
            'share'    => $sourcedSearches ? round($row->searches / $sourcedSearches * 100, 1) : 0,
        ])->all();
    }

    protected function groupedQueries(Builder $query): Builder
    {
        return $query
            ->whereRaw('char_length(query) >= 3')
            ->selectRaw('lower(query) as query, count(*) as searches, count(clicked_at) as clicks')
            ->groupByRaw('lower(query)');
    }
}
