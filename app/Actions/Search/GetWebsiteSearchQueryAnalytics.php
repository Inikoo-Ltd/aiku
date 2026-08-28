<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 02 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Helpers\WebsiteSearchLog;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteSearchQueryAnalytics
{
    use AsObject;

    public function handle(Website $website, string $query, int $days = 30): array
    {
        $base = WebsiteSearchLog::where('website_search_logs.website_id', $website->id)
            ->whereRaw('lower(website_search_logs.query) = ?', [mb_strtolower($query)])
            ->where('website_search_logs.created_at', '>=', now()->subDays($days));

        $totalSearches = (clone $base)->count();
        $clicked       = (clone $base)->whereNotNull('clicked_at')->count();
        $zeroResults   = (clone $base)->where('keyword_results_count', 0)->count();
        $customers     = (clone $base)->whereNotNull('customer_id')->distinct()->count('customer_id');
        $loggedIn      = (clone $base)->whereNotNull('web_user_id')->count();

        $topPages = (clone $base)
            ->whereNotNull('clicked_url')
            ->selectRaw('clicked_url, count(*) as clicks')
            ->groupBy('clicked_url')
            ->orderByDesc('clicks')
            ->limit(10)
            ->get();

        $topCustomers = (clone $base)
            ->join('customers', 'customers.id', '=', 'website_search_logs.customer_id')
            ->selectRaw('customers.name as username, customers.slug as customer_slug, count(*) as searches, count(website_search_logs.clicked_at) as clicks')
            ->groupBy('customers.name', 'customers.slug')
            ->orderByDesc('searches')
            ->limit(10)
            ->get();

        $devices = (clone $base)
            ->whereNotNull('device')
            ->selectRaw('device, count(*) as searches, count(clicked_at) as clicks')
            ->groupBy('device')
            ->orderByDesc('searches')
            ->get();

        return [
            'days'               => $days,
            'query'              => $query,
            'total_searches'     => $totalSearches,
            'unique_customers'   => $customers,
            'logged_in_searches' => $loggedIn,
            'guest_searches'     => $totalSearches - $loggedIn,
            'click_through'      => $totalSearches ? round($clicked / $totalSearches * 100, 1) : 0,
            'zero_results_rate'  => $totalSearches ? round($zeroResults / $totalSearches * 100, 1) : 0,
            'avg_results'        => round((float)(clone $base)->avg('results_count'), 1),
            'top_clicked_pages'  => $topPages,
            'top_searchers'      => $topCustomers,
            'devices'            => $devices,
            'trend'              => GetWebsiteSearchTrend::run($website, $days, ['query' => $query]),
        ];
    }
}
