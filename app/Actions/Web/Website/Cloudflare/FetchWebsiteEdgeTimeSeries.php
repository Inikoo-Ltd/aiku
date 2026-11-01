<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Cloudflare;

use App\Http\Middleware\CountOctaneStorefrontPage;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Web\Website;
use App\Models\Web\WebsiteEdgeDatacenterDay;
use App\Models\Web\WebsiteEdgeTimeSeries;
use App\Traits\BuildsAggregatedTimeSeriesQuery;
use App\Traits\UpsertsTimeSeriesRecords;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Records, per live website and per UTC day, what Cloudflare did with its storefront HTML
 * requests: cache status, status class, bots vs browsers and the far scraper countries, and per
 * Cloudflare datacenter how many it answered from its own edge copy or rules. Redirects Cloudflare
 * makes itself (http→https, www, redirect rules) are left out: the page they lead to is a request of
 * its own, so counting both would count one visit twice.
 *
 * Cloudflare's Free plan keeps these analytics for 31 days and a query may span 30 at most, so
 * windows are cut into chunks and never start before the first whole day still kept: a day
 * Cloudflare has partly dropped would be stored as a traffic collapse nobody could correct.
 * Today is never stored, and the nightly run re-fetches the last few whole days, so a run that
 * fails or a day Cloudflare was still ingesting gets corrected by the next one.
 * Daily rows are only ever upserted (the stored row is the last copy); the weekly to yearly
 * rows are rebuilt from them.
 */
class FetchWebsiteEdgeTimeSeries
{
    use AsAction;
    use BuildsAggregatedTimeSeriesQuery;
    use UpsertsTimeSeriesRecords;

    public const array FAR_COUNTRIES = ['US', 'SG', 'CN', 'HK', 'JP', 'MX', 'KR', 'BR'];

    public const array METRICS = [
        'requests',
        'requests_hit',
        'requests_miss',
        'requests_expired',
        'requests_dynamic',
        'requests_bypass',
        'requests_none',
        'requests_other_cache_status',
        'origin_response_ms_total',
        'requests_2xx',
        'requests_3xx',
        'requests_4xx',
        'requests_5xx',
        'requests_google_bot',
        'requests_bing_bot',
        'requests_apple_bot',
        'requests_other_bots',
        'requests_unknown_agent',
        'requests_browsers',
        'requests_far_countries',
        'requests_far_countries_hit',
    ];

    private const int RETENTION_SECONDS = 2678400;

    private const int ZONES_PER_QUERY = 10;

    private const int DAYS_PER_QUERY = 29;

    private const int REFETCH_DAYS = 4;

    private const int ROWS_PER_GROUP = 10000;

    private const array DATACENTER_GROUPS = ['colo', 'coloHit', 'coloNone'];

    /**
     * @var array<string, array<int|string, string>|null>
     */
    private array $octanePagesByDate = [];

    public string $commandSignature = 'websites:fetch_edge_time_series {--from= : First UTC day (Y-m-d), default 4 days ago} {--to= : Last UTC day (Y-m-d), default and at most yesterday} {--website= : Website slug}';

    /**
     * @return array{days: int, errors: array<int, string>}
     */
    public function handle(Carbon $from, Carbon $to, ?string $websiteSlug = null): array
    {
        $from = $from->copy()->startOfDay()->max($this->firstWholeDayKept());
        $to   = $to->copy()->startOfDay()->min(now()->subDay()->startOfDay());

        $errors = [];
        $days   = 0;

        if ($websiteSlug && !Website::where('slug', $websiteSlug)->where('state', WebsiteStateEnum::LIVE)->exists()) {
            return ['days' => 0, 'errors' => ["no live website with slug $websiteSlug"]];
        }

        if ($from->gt($to)) {
            return ['days' => 0, 'errors' => ["nothing to fetch: Cloudflare keeps whole days from {$this->firstWholeDayKept()->toDateString()} only"]];
        }

        $websites = Website::where('state', WebsiteStateEnum::LIVE)
            ->whereNotNull('cloudflare_zone_id')
            ->whereNotNull('cloudflare_token')
            ->when($websiteSlug, fn ($query) => $query->where('slug', $websiteSlug))
            ->orderBy('id')
            ->get();

        if ($websiteSlug && $websites->isEmpty()) {
            return ['days' => 0, 'errors' => ["$websiteSlug has no Cloudflare zone id or token"]];
        }

        $websitesByToken = [];
        foreach ($websites as $website) {
            try {
                $websitesByToken[decrypt($website->cloudflare_token)][] = $website;
            } catch (Throwable) {
                $errors[] = "$website->slug: cloudflare_token cannot be decrypted";
            }
        }

        $fetchedWebsites = [];
        foreach ($websitesByToken as $token => $tokenWebsites) {
            foreach (array_chunk($tokenWebsites, self::ZONES_PER_QUERY) as $batch) {
                for ($windowFrom = $from->copy(); $windowFrom->lte($to); $windowFrom->addDays(self::DAYS_PER_QUERY)) {
                    $windowTo = $windowFrom->copy()->addDays(self::DAYS_PER_QUERY - 1)->min($to);

                    foreach ($this->fetchZones($token, $batch, $windowFrom, $windowTo, $errors) as [$website, $zone]) {
                        $days += $this->storeDailyRows($website, $this->dailyRows($zone));
                        $this->storeDatacenterRows($website, $this->datacenterRows($zone));
                        $fetchedWebsites[$website->id] = $website;
                    }
                }
            }
        }

        foreach ($fetchedWebsites as $website) {
            $this->rollUp($website, $from, $to);
        }

        return ['days' => $days, 'errors' => $errors];
    }

    /**
     * One Cloudflare error fails the whole zoneTag_in batch, so a failed batch is retried zone by
     * zone: a single zone the token cannot read must not cost the others their days.
     *
     * @param array<int, Website> $websites
     * @param array<int, string> $errors
     *
     * @return array<int, array{0: Website, 1: array<string, mixed>}>
     */
    protected function fetchZones(string $token, array $websites, Carbon $from, Carbon $to, array &$errors): array
    {
        $websitesByZone = collect($websites)->keyBy('cloudflare_zone_id');
        $label          = $websitesByZone->pluck('slug')->implode(', ')." {$from->toDateString()}..{$to->toDateString()}";

        try {
            $response = Http::withToken($token)
                ->timeout(60)
                ->post('https://api.cloudflare.com/client/v4/graphql', [
                    'query' => $this->query($websitesByZone->keys()->all(), $from, $to),
                ]);
            $failure = $response->failed() || $response->json('errors')
                ? (collect($response->json('errors') ?: [])->pluck('message')->implode('; ') ?: 'HTTP '.$response->status())
                : null;
        } catch (Throwable $e) {
            $failure = $e->getMessage();
        }

        if ($failure !== null) {
            if (count($websites) > 1) {
                $zones = [];
                foreach ($websites as $website) {
                    array_push($zones, ...$this->fetchZones($token, [$website], $from, $to, $errors));
                }

                return $zones;
            }
            $errors[] = "$label: $failure";

            return [];
        }

        $zones = collect($response->json('data.viewer.zones') ?? [])->keyBy('zoneTag');
        foreach ($websitesByZone->keys()->diff($zones->keys()) as $missingZone) {
            $errors[] = "{$websitesByZone[$missingZone]->slug} {$from->toDateString()}..{$to->toDateString()}: zone not in Cloudflare's answer (zone id wrong, or the token cannot read its analytics)";
        }

        foreach ($zones as $zoneTag => $zone) {
            foreach (['cache', 'status', 'agent', 'far'] as $group) {
                if (count($zone[$group] ?? []) >= self::ROWS_PER_GROUP) {
                    $errors[] = "{$websitesByZone[$zoneTag]->slug} {$from->toDateString()}..{$to->toDateString()}: $group hit Cloudflare's ".self::ROWS_PER_GROUP." row limit, not stored; rerun with a shorter --from/--to";
                    $zones->forget($zoneTag);
                    continue 2;
                }
            }
            foreach (self::DATACENTER_GROUPS as $group) {
                if (count($zone[$group] ?? []) >= self::ROWS_PER_GROUP) {
                    $errors[] = "{$websitesByZone[$zoneTag]->slug} {$from->toDateString()}..{$to->toDateString()}: datacenters hit Cloudflare's ".self::ROWS_PER_GROUP." row limit, not stored; rerun with a shorter --from/--to";
                    $zones[$zoneTag] = array_diff_key($zone, array_flip(self::DATACENTER_GROUPS));
                    break;
                }
            }
        }

        return $zones->only($websitesByZone->keys()->all())
            ->map(fn (array $zone, string $zoneTag) => [$websitesByZone[$zoneTag], $zone])
            ->values()
            ->all();
    }

    public function firstWholeDayKept(): Carbon
    {
        return now()->subSeconds(self::RETENTION_SECONDS)->addMinutes(10)->ceilDay();
    }

    /**
     * @param array<int, string> $zoneTags
     */
    protected function query(array $zoneTags, Carbon $from, Carbon $to): string
    {
        $zones   = json_encode($zoneTags);
        $filter  = sprintf('date_geq: "%s", date_leq: "%s", requestSource: "eyeball", edgeResponseContentTypeName: "html", OR: [{cacheStatus_neq: "none"}, {edgeResponseStatus_notin: [301, 302, 303, 307, 308]}]', $from->toDateString(), $to->toDateString());
        $far     = json_encode(self::FAR_COUNTRIES);
        $hit     = json_encode(['hit', 'stale', 'updating', 'revalidated']);
        $rowsPerGroup = self::ROWS_PER_GROUP;

        return <<<GRAPHQL
        {
          viewer {
            zones(filter: {zoneTag_in: $zones}) {
              zoneTag
              cache: httpRequestsAdaptiveGroups(limit: $rowsPerGroup, filter: {{$filter}}) { count avg { originResponseDurationMs } dimensions { date cacheStatus } }
              status: httpRequestsAdaptiveGroups(limit: $rowsPerGroup, filter: {{$filter}}) { count dimensions { date edgeResponseStatus } }
              agent: httpRequestsAdaptiveGroups(limit: $rowsPerGroup, filter: {{$filter}}) { count dimensions { date userAgentBrowser } }
              far: httpRequestsAdaptiveGroups(limit: $rowsPerGroup, filter: {{$filter}, clientCountryName_in: $far}) { count dimensions { date cacheStatus } }
              colo: httpRequestsAdaptiveGroups(limit: $rowsPerGroup, filter: {{$filter}}) { count dimensions { date coloCode } }
              coloHit: httpRequestsAdaptiveGroups(limit: $rowsPerGroup, filter: {{$filter}, cacheStatus_in: $hit}) { count dimensions { date coloCode } }
              coloNone: httpRequestsAdaptiveGroups(limit: $rowsPerGroup, filter: {{$filter}, cacheStatus: "none"}) { count dimensions { date coloCode } }
            }
          }
        }
        GRAPHQL;
    }

    /**
     * @param array<string, mixed> $zone
     *
     * @return array<string, array<string, int>>
     */
    public function dailyRows(array $zone): array
    {
        $rows = [];
        $add  = function (string $date, string $column, int|float $amount) use (&$rows) {
            $rows[$date] ??= array_fill_keys(self::METRICS, 0);
            $rows[$date][$column] += (int) round($amount);
        };

        foreach ($zone['cache'] ?? [] as $group) {
            $date   = $group['dimensions']['date'];
            $column = $this->cacheStatusColumn($group['dimensions']['cacheStatus']);
            $add($date, 'requests', $group['count']);
            $add($date, $column, $group['count']);

            $originMs = $group['avg']['originResponseDurationMs'] ?? 0;
            if ($originMs > 0 && !in_array($column, ['requests_hit', 'requests_none'])) {
                $add($date, 'origin_response_ms_total', $originMs * $group['count']);
            }
        }

        foreach ($zone['status'] ?? [] as $group) {
            $class = intdiv((int) $group['dimensions']['edgeResponseStatus'], 100);
            if ($class >= 2 && $class <= 5) {
                $add($group['dimensions']['date'], "requests_{$class}xx", $group['count']);
            }
        }

        foreach ($zone['agent'] ?? [] as $group) {
            $add($group['dimensions']['date'], $this->userAgentColumn((string) $group['dimensions']['userAgentBrowser']), $group['count']);
        }

        foreach ($zone['far'] ?? [] as $group) {
            $date = $group['dimensions']['date'];
            $add($date, 'requests_far_countries', $group['count']);
            if ($this->cacheStatusColumn($group['dimensions']['cacheStatus']) === 'requests_hit') {
                $add($date, 'requests_far_countries_hit', $group['count']);
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $zone
     *
     * @return array<string, array{date: string, colo: string, requests: int, requests_hit: int, requests_none: int}>
     */
    public function datacenterRows(array $zone): array
    {
        $rows = [];
        foreach (['colo' => 'requests', 'coloHit' => 'requests_hit', 'coloNone' => 'requests_none'] as $group => $column) {
            foreach ($zone[$group] ?? [] as $row) {
                $date = $row['dimensions']['date'];
                $colo = (string) $row['dimensions']['coloCode'];
                $key  = "$date $colo";
                $rows[$key] ??= ['date' => $date, 'colo' => $colo, 'requests' => 0, 'requests_hit' => 0, 'requests_none' => 0];
                $rows[$key][$column] += (int) $row['count'];
            }
        }

        return $rows;
    }

    protected function cacheStatusColumn(string $cacheStatus): string
    {
        return match ($cacheStatus) {
            'hit', 'stale', 'updating', 'revalidated' => 'requests_hit',
            'miss', 'expired', 'dynamic', 'bypass', 'none' => "requests_$cacheStatus",
            default => 'requests_other_cache_status',
        };
    }

    protected function userAgentColumn(string $userAgentBrowser): string
    {
        return match (true) {
            $userAgentBrowser === 'GoogleBot' => 'requests_google_bot',
            $userAgentBrowser === 'BingBot' => 'requests_bing_bot',
            $userAgentBrowser === 'AppleBot' => 'requests_apple_bot',
            $userAgentBrowser === 'Unknown', $userAgentBrowser === '' => 'requests_unknown_agent',
            (bool) preg_match('/bot|spider|crawl|headless|curl|wget|python|scrapy|http|java|okhttp|axios|libwww|perl|ruby|php/i', $userAgentBrowser) => 'requests_other_bots',
            default => 'requests_browsers',
        };
    }

    /**
     * @param array<string, array<string, int>> $rowsByDate
     */
    protected function storeDailyRows(Website $website, array $rowsByDate): int
    {
        $timeSeries = $this->timeSeries($website, TimeSeriesFrequencyEnum::DAILY);

        $rows = [];
        foreach ($rowsByDate as $date => $metrics) {
            $day    = Carbon::parse($date);
            $rows[] = [
                'website_edge_time_series_id' => $timeSeries->id,
                'period'                      => $day->toDateString(),
                'frequency'                   => TimeSeriesFrequencyEnum::DAILY->singleLetter(),
                'from'                        => $day->copy()->startOfDay(),
                'to'                          => $day->copy()->endOfDay(),
                ...$metrics,
            ];
        }

        $this->upsertTimeSeriesRecords($timeSeries, $rows, ['website_edge_time_series_id', 'period', 'frequency']);

        foreach (array_keys($rowsByDate) as $date) {
            $octanePages = $this->octanePages($date);
            if ($octanePages !== null) {
                DB::table('website_edge_time_series_records')
                    ->where('website_edge_time_series_id', $timeSeries->id)
                    ->where('frequency', TimeSeriesFrequencyEnum::DAILY->singleLetter())
                    ->where('period', $date)
                    ->update(['requests_octane' => (int) ($octanePages[$website->id] ?? 0)]);
            }
        }

        return count($rows);
    }

    /**
     * @param array<string, array{date: string, colo: string, requests: int, requests_hit: int, requests_none: int}> $rows
     */
    protected function storeDatacenterRows(Website $website, array $rows): void
    {
        $now = now();
        foreach (array_chunk(array_values($rows), 1000) as $chunk) {
            WebsiteEdgeDatacenterDay::upsert(
                array_map(fn (array $row) => ['website_id' => $website->id, ...$row, 'created_at' => $now, 'updated_at' => $now], $chunk),
                ['website_id', 'date', 'colo'],
                ['requests', 'requests_hit', 'requests_none', 'updated_at']
            );
        }
    }

    /**
     * Pages Octane rendered per website id on a UTC day, or null when that day was not counted
     * whole: no counter for it, or none for the day before, so counting (or Redis) started during
     * it. Its stored value then stays.
     *
     * @return array<int|string, string>|null
     */
    protected function octanePages(string $date): ?array
    {
        if (!array_key_exists($date, $this->octanePagesByDate)) {
            $day                            = Carbon::parse($date);
            $key                            = CountOctaneStorefrontPage::key($day);
            $countedWhole                   = Redis::exists($key) && Redis::exists(CountOctaneStorefrontPage::key($day->copy()->subDay()));
            $this->octanePagesByDate[$date] = $countedWhole ? Redis::hgetall($key) : null;
        }

        return $this->octanePagesByDate[$date];
    }

    protected function rollUp(Website $website, Carbon $from, Carbon $to): void
    {
        $daily = $this->timeSeries($website, TimeSeriesFrequencyEnum::DAILY);

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($frequency === TimeSeriesFrequencyEnum::DAILY) {
                continue;
            }

            [$periodFrom, $periodTo] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from->toDateString(), $to->toDateString());
            $timeSeries              = $this->timeSeries($website, $frequency);

            $query = DB::table('website_edge_time_series_records')
                ->where('website_edge_time_series_id', $daily->id)
                ->where('from', '>=', $periodFrom)
                ->where('to', '<=', $periodTo);

            $sums    = [...array_map(fn (string $metric) => DB::raw("SUM($metric) as $metric"), self::METRICS), DB::raw('CASE WHEN COUNT(requests_octane) = COUNT(*) THEN SUM(requests_octane) END as requests_octane')];
            $results = $this->applyAggregatedFrequencyGrouping($query, $frequency, $sums)->get();

            $this->upsertTimeSeriesRecords($timeSeries, $this->rollUpRows($timeSeries, $results), ['website_edge_time_series_id', 'period', 'frequency']);
            $timeSeries->update(['number_records' => $timeSeries->records()->count()]);
        }

        $daily->update(['number_records' => $daily->records()->count()]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rollUpRows(WebsiteEdgeTimeSeries $timeSeries, Collection $results): array
    {
        return $results->map(function (object $result) use ($timeSeries) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            return [
                'website_edge_time_series_id' => $timeSeries->id,
                'period'                      => $period,
                'frequency'                   => $timeSeries->frequency->singleLetter(),
                'from'                        => $periodFrom,
                'to'                          => $periodTo,
                ...array_map('intval', array_intersect_key((array) $result, array_flip(self::METRICS))),
                'requests_octane'             => $result->requests_octane === null ? null : (int) $result->requests_octane,
            ];
        })->all();
    }

    protected function timeSeries(Website $website, TimeSeriesFrequencyEnum $frequency): WebsiteEdgeTimeSeries
    {
        return WebsiteEdgeTimeSeries::firstOrCreate(['website_id' => $website->id, 'frequency' => $frequency]);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $from = Carbon::parse($command->option('from') ?? now()->subDays(self::REFETCH_DAYS)->toDateString());
        if ($from->lt($this->firstWholeDayKept())) {
            $command->warn("Cloudflare keeps whole days from {$this->firstWholeDayKept()->toDateString()} only, starting there");
        }

        $result = $this->handle(
            $from,
            Carbon::parse($command->option('to') ?? now()->subDay()->toDateString()),
            $command->option('website')
        );

        foreach ($result['errors'] as $error) {
            $command->error($error);
        }
        $command->info("{$result['days']} website days stored");

        return $result['errors'] ? Command::FAILURE : Command::SUCCESS;
    }
}
