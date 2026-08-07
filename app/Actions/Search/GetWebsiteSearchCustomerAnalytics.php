<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 02 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\CRM\Customer;
use App\Models\Helpers\WebsiteSearchLog;
use App\Models\Web\Website;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteSearchCustomerAnalytics
{
    use AsObject;

    public function handle(Website $website, Customer $customer, int $days = 30): array
    {
        $base = WebsiteSearchLog::where('website_id', $website->id)
            ->where('customer_id', $customer->id)
            ->where('created_at', '>=', now()->subDays($days));

        $totalSearches = (clone $base)->count();
        $clicked       = (clone $base)->whereNotNull('clicked_at')->count();
        $zeroResults   = (clone $base)->where('keyword_results_count', 0)->count();

        $topQueries = $this->groupedQueries(clone $base)
            ->orderByDesc('searches')
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

        $topPages = (clone $base)
            ->whereNotNull('clicked_url')
            ->selectRaw('clicked_url, count(*) as clicks')
            ->groupBy('clicked_url')
            ->orderByDesc('clicks')
            ->limit(10)
            ->get();

        $devices = (clone $base)
            ->whereNotNull('device')
            ->selectRaw('device, count(*) as searches, count(clicked_at) as clicks')
            ->groupBy('device')
            ->orderByDesc('searches')
            ->get();

        return [
            'days'                  => $days,
            'customer_name'         => $customer->name,
            'total_searches'        => $totalSearches,
            'click_through'         => $totalSearches ? round($clicked / $totalSearches * 100, 1) : 0,
            'zero_results_rate'     => $totalSearches ? round($zeroResults / $totalSearches * 100, 1) : 0,
            'top_queries'           => $topQueries,
            'top_zero_queries'      => $topZeroQueries,
            'top_abandoned_queries' => $topAbandonedQueries,
            'top_clicked_pages'     => $topPages,
            'devices'               => $devices,
            'trend'                 => GetWebsiteSearchTrend::run($website, $days, ['customer_id' => $customer->id]),
        ];
    }

    protected function groupedQueries(Builder $query): Builder
    {
        return $query
            ->selectRaw('lower(query) as query, count(*) as searches, count(clicked_at) as clicks')
            ->groupByRaw('lower(query)');
    }
}
