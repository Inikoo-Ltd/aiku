<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Helpers\WebsiteSearchLog;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteSearchPageAnalytics
{
    use AsObject;

    public function handle(Website $website, string $clickedUrl, int $days = 30): array
    {
        $base = WebsiteSearchLog::where('website_search_logs.website_id', $website->id)
            ->where('website_search_logs.clicked_url', $clickedUrl)
            ->where('website_search_logs.created_at', '>=', now()->subDays($days));

        $totalClicks = (clone $base)->count();
        $customers   = (clone $base)->whereNotNull('customer_id')->distinct()->count('customer_id');
        $loggedIn    = (clone $base)->whereNotNull('web_user_id')->count();

        $topQueries = (clone $base)
            ->selectRaw('lower(query) as query, count(*) as clicks')
            ->groupByRaw('lower(query)')
            ->orderByDesc('clicks')
            ->limit(10)
            ->get();

        $topCustomers = (clone $base)
            ->join('customers', 'customers.id', '=', 'website_search_logs.customer_id')
            ->selectRaw('customers.name as username, customers.slug as customer_slug, count(*) as clicks')
            ->groupBy('customers.name', 'customers.slug')
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
            'days'             => $days,
            'clicked_url'      => $clickedUrl,
            'total_clicks'     => $totalClicks,
            'unique_customers' => $customers,
            'logged_in'        => $loggedIn,
            'guest'            => $totalClicks - $loggedIn,
            'top_queries'      => $topQueries,
            'top_searchers'    => $topCustomers,
            'devices'          => $devices,
            'trend'            => GetWebsiteSearchTrend::run($website, $days, ['clicked_url' => $clickedUrl]),
        ];
    }
}
