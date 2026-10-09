<?php

namespace App\Actions\Web\WebsiteVisitor;

use App\Actions\OrgAction;
use App\Actions\Web\WebsitePageView\GetWebsiteEntryPageViews;
use App\Enums\Web\WebsiteConversionEvent\WebsiteConversionEventTypeEnum;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

        $pageType = Arr::get($params, 'page_type');

        $days      = max(1, (int) $since->copy()->startOfDay()->diffInDays($until->copy()->startOfDay()) + 1);
        $prevUntil = $since->copy()->subDay();
        $prevSince = $prevUntil->copy()->subDays($days - 1);

        $currentStats  = $this->getStats($website, $since, $until);
        $previousStats = $this->getStats($website, $prevSince, $prevUntil);

        $webpages = DB::table('webpages')
            ->whereIn('id', $currentStats->keys())
            ->when($pageType, fn ($query) => $query->where('type', $pageType))
            ->get(['id', 'url', 'canonical_url'])
            ->keyBy('id');

        $results = [];

        foreach ($webpages as $webpageId => $webpage) {
            $current  = $currentStats->get($webpageId);
            $previous = $previousStats->get($webpageId);

            $currentRate  = $this->rate($current['purchases'], $current['entrances']);
            $previousRate = $previous ? $this->rate($previous['purchases'], $previous['entrances']) : 0;
            $trend        = $currentRate - $previousRate;

            $results[] = [
                'page_path'         => '/'.ltrim((string) $webpage->url, '/'),
                'page_url'          => $webpage->canonical_url ?: $webpage->url,
                'conversion_rate'   => $currentRate,
                'total_conversions' => $current['purchases'],
                'checkouts'         => $current['checkouts'],
                'add_to_baskets'    => $current['add_to_baskets'],
                'entrances'         => $current['entrances'],
                'total_visits'      => $current['visits'],
                'avg_time_spent'    => round($current['avg_duration']),
                'trend'             => [
                    'direction' => $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'neutral'),
                    'value'     => round(abs($trend), 2),
                    'prev_rate' => $previousRate,
                ],
            ];
        }

        usort($results, fn ($a, $b) => [$b['total_conversions'], $b['entrances']] <=> [$a['total_conversions'], $a['entrances']]);

        return $results;
    }

    /**
     * @return Collection<int, array{visits: int, avg_duration: float, entrances: int, add_to_baskets: int, checkouts: int, purchases: int}>
     */
    private function getStats(Website $website, Carbon $since, Carbon $until): Collection
    {
        $from = $since->toDateString();
        $to   = $until->toDateString();

        $visits = DB::connection('aiku_no_sticky')->table('website_page_views')
            ->where('website_id', $website->id)
            ->whereNotNull('webpage_id')
            ->whereBetween('view_date', [$from, $to])
            ->groupBy('webpage_id')
            ->select('webpage_id')
            ->selectRaw('COUNT(*) as visits, AVG(duration_seconds) as avg_duration')
            ->get()
            ->keyBy('webpage_id');

        $entrances = GetWebsiteEntryPageViews::run()
            ->where('entry_views.website_id', $website->id)
            ->whereNotNull('entry_views.webpage_id')
            ->whereBetween('entry_views.view_date', [$from, $to])
            ->groupBy('entry_views.webpage_id')
            ->select('entry_views.webpage_id')
            ->selectRaw('COUNT(DISTINCT (entry_visitors.visitor_hash, entry_views.view_date)) as entrances')
            ->get()
            ->keyBy('webpage_id');

        $addToBaskets = DB::connection('aiku_no_sticky')->table('website_conversion_events')
            ->where('website_id', $website->id)
            ->where('event_type', WebsiteConversionEventTypeEnum::ADD_TO_BASKET->value)
            ->whereNotNull('webpage_id')
            ->whereBetween('event_date', [$from, $to])
            ->groupBy('webpage_id')
            ->select('webpage_id')
            ->selectRaw('COUNT(*) as add_to_baskets')
            ->get()
            ->keyBy('webpage_id');

        $checkout = WebsiteConversionEventTypeEnum::CHECKOUT->value;
        $purchase = WebsiteConversionEventTypeEnum::PURCHASE->value;

        $landings = DB::connection('aiku_no_sticky')->table('website_conversion_events')
            ->where('website_id', $website->id)
            ->whereIn('event_type', [$checkout, $purchase])
            ->whereNotNull('landing_webpage_id')
            ->whereBetween('event_date', [$from, $to])
            ->groupBy('landing_webpage_id')
            ->select('landing_webpage_id')
            ->selectRaw("COUNT(*) FILTER (WHERE event_type = '$checkout') as checkouts")
            ->selectRaw("COUNT(*) FILTER (WHERE event_type = '$purchase') as purchases")
            ->get()
            ->keyBy('landing_webpage_id');

        return $visits->keys()
            ->merge($entrances->keys())
            ->merge($addToBaskets->keys())
            ->merge($landings->keys())
            ->unique()
            ->mapWithKeys(fn ($webpageId) => [
                $webpageId => [
                    'visits'         => (int) ($visits->get($webpageId)->visits ?? 0),
                    'avg_duration'   => (float) ($visits->get($webpageId)->avg_duration ?? 0),
                    'entrances'      => (int) ($entrances->get($webpageId)->entrances ?? 0),
                    'add_to_baskets' => (int) ($addToBaskets->get($webpageId)->add_to_baskets ?? 0),
                    'checkouts'      => (int) ($landings->get($webpageId)->checkouts ?? 0),
                    'purchases'      => (int) ($landings->get($webpageId)->purchases ?? 0),
                ],
            ]);
    }

    private function rate(int $purchases, int $entrances): float
    {
        return $entrances > 0 ? round($purchases / $entrances * 100, 2) : 0;
    }
}
