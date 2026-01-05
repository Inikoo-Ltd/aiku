<?php

namespace App\Actions\Web\WebsiteVisitor;

use App\Actions\OrgAction;
use App\Models\Web\Website;
use App\Models\Web\WebsiteVisitor;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWebsiteVisitorAnalytics extends OrgAction
{
    use AsAction;

    public function handle(Website $website, array $params = []): array
    {
        $since = Arr::get($params, 'since')
            ? Carbon::parse($params['since'])
            : Carbon::now()->subHours(24);

        $until = Arr::get($params, 'until')
            ? Carbon::parse($params['until'])
            : Carbon::now();

        // 1. Total Unique Visitors (based on visitor_hash)
        $totalVisitors = WebsiteVisitor::query()
            ->where('website_id', $website->id)
            ->whereBetween('first_seen_at', [$since, $until])
            ->distinct('visitor_hash')
            ->count('visitor_hash');

        $totalPageViews = WebsiteVisitor::query()
            ->where('website_id', $website->id)
            ->whereBetween('first_seen_at', [$since, $until])
            ->sum('page_views');

        // 2. Time Series Data (grouped by 15-minute intervals)
        // We will use DB::raw to group by time intervals.
        // For MySQL/PostgreSQL compatibility, we might need different syntax.
        // Assuming MySQL/MariaDB based on typical Laravel setups, or we can do it in PHP to be safe if dataset isn't huge.
        // Given "Visitors" implies distinct users, doing this in SQL with grouping is better.

        // Determine interval. 15 mins = 900 seconds.
        // MySQL: FLOOR(UNIX_TIMESTAMP(first_seen_at) / 900) * 900

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite approach (for local dev/testing if applicable)
            $select = "strftime('%Y-%m-%d %H:%M:00', datetime((strftime('%s', first_seen_at) / 900) * 900, 'unixepoch')) as timeslot";
        } elseif ($driver === 'pgsql') {
            // PostgreSQL approach
            $select = "to_char(to_timestamp(floor(extract(epoch from first_seen_at) / 900) * 900), 'YYYY-MM-DD HH24:MI:SS') as timeslot";
        } else {
            // MySQL/MariaDB approach
            $select = "FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(first_seen_at) / 900) * 900) as timeslot";
        }
        $timeSeriesData = WebsiteVisitor::query()
            ->select(
                DB::raw("$select"),
                DB::raw('COUNT(DISTINCT visitor_hash) as visitors'),
                DB::raw('SUM(page_views) as page_views')
            )
            ->where('website_id', $website->id)
            ->whereBetween('first_seen_at', [$since, $until])
            ->groupBy('timeslot')
            ->orderBy('timeslot')
            ->get();

        $timeSeries = $timeSeriesData->map(function ($item) {
            return [
                'timestamp'  => Carbon::parse($item->timeslot)->toIso8601String(),
                'visitors'   => $item->visitors,
                'page_views' => $item->page_views,
            ];
        });

        return [
            'totalVisitors'  => $totalVisitors,
            'totalPageViews' => $totalPageViews,
            'timeSeries'     => $timeSeries,
        ];
    }
}
