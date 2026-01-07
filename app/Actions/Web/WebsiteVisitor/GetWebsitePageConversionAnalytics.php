<?php

namespace App\Actions\Web\WebsiteVisitor;

use App\Actions\OrgAction;
use App\Enums\Web\WebsiteConversionEvent\WebsiteConversionEventTypeEnum;
use App\Models\Web\Website;
use App\Models\Web\WebsiteConversionEvent;
use App\Models\Web\WebsitePageView;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWebsitePageConversionAnalytics extends OrgAction
{
    use AsAction;

    public function handle(Website $website, array $params = []): array
    {
        $since = Arr::get($params, 'since')
            ? Carbon::parse($params['since'])
            : Carbon::now()->subDays(30);

        $until = Arr::get($params, 'until')
            ? Carbon::parse($params['until'])
            : Carbon::now();

        // Current Period Data
        $currentStats = $this->getStats($website, $since, $until, $params);

        // Previous Period Data (for trend)
        // Calculate duration to shift back
        $diffInSeconds = $since->diffInSeconds($until);
        // Avoid zero duration
        if ($diffInSeconds < 86400) {
            $diffInSeconds = 86400; // Minimum 1 day for trend comparison context if range is too small
        }

        $prevUntil = $since->copy()->subSecond();
        $prevSince = $prevUntil->copy()->subSeconds($diffInSeconds);

        $prevStats = $this->getStats($website, $prevSince, $prevUntil, $params);

        // Merge and Format
        $results = [];
        $allPaths = $currentStats->keys()->merge($prevStats->keys())->unique();

        foreach ($allPaths as $path) {
            $curr = $currentStats->get($path);
            $prev = $prevStats->get($path);

            // Current Metrics
            $currVisits = $curr['visits'] ?? 0;
            $currConversions = $curr['conversions'] ?? 0;
            $currAvgDuration = $curr['avg_duration'] ?? 0;
            $currRate = $currVisits > 0 ? ($currConversions / $currVisits) * 100 : 0;

            // Previous Metrics
            $prevVisits = $prev['visits'] ?? 0;
            $prevConversions = $prev['conversions'] ?? 0;
            $prevRate = $prevVisits > 0 ? ($prevConversions / $prevVisits) * 100 : 0;

            // Trend Calculation (Difference in Percentage Points)
            $trend = $currRate - $prevRate;
            $trendDirection = $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'neutral');

            $results[] = [
                'page_path' => $path,
                // Use page_url from current if available, else path
                'page_url' => $curr['url'] ?? $path,
                'conversion_rate' => round($currRate, 2),
                'total_conversions' => $currConversions,
                'total_visits' => $currVisits,
                'avg_time_spent' => round($currAvgDuration, 0),
                'trend' => [
                    'direction' => $trendDirection,
                    'value' => round(abs($trend), 2),
                    'prev_rate' => round($prevRate, 2)
                ]
            ];
        }

        // Sort by Total Conversions Descending by default
        usort($results, fn ($a, $b) => $b['total_conversions'] <=> $a['total_conversions']);

        return $results;
    }

    private function getStats(Website $website, Carbon $since, Carbon $until, array $params = [])
    {
        $pageType = Arr::get($params, 'page_type');

        // 1. Visits
        $visitsQuery = WebsitePageView::query()
            ->select(
                'page_path',
                // taking the first page_url found for display purposes
                DB::raw('MIN(page_url) as page_url_display'),
                DB::raw('count(*) as total_visits'),
                DB::raw('avg(duration_seconds) as avg_duration')
            )
            ->where('website_id', $website->id)
            ->whereBetween('view_date', [$since, $until]);

        if ($pageType) {
            $visitsQuery->where('page_type', $pageType);
        }

        $visits = $visitsQuery->groupBy('page_path')
            ->get()
            ->keyBy('page_path');

        // 2. Conversions
        $conversionsQuery = WebsiteConversionEvent::query()
            ->select('website_conversion_events.page_path', DB::raw('count(*) as total_conversions'))
            ->where('website_conversion_events.website_id', $website->id)
            ->where('website_conversion_events.event_type', WebsiteConversionEventTypeEnum::ADD_TO_BASKET)
            ->whereBetween('website_conversion_events.event_date', [$since, $until]);

        if ($pageType) {
            $conversionsQuery->join('webpages', 'webpages.id', '=', 'website_conversion_events.webpage_id')
                ->where('webpages.type', $pageType);
        }

        $conversions = $conversionsQuery->groupBy('website_conversion_events.page_path')
            ->get()
            ->keyBy('page_path');

        $merged = collect();
        $allPaths = $visits->keys()->merge($conversions->keys())->unique();

        foreach ($allPaths as $path) {
            $v = $visits->get($path);
            $c = $conversions->get($path);

            $merged->put($path, [
                'url' => $v ? $v->page_url_display : $path,
                'visits' => $v ? $v->total_visits : 0,
                'avg_duration' => $v ? $v->avg_duration : 0,
                'conversions' => $c ? $c->total_conversions : 0,
            ]);
        }

        return $merged;
    }
}
