<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\Web\Website\Cloudflare\FetchWebsiteEdgeTimeSeries;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Daily storefront traffic of the group's live websites, or of one organisation or website:
 * what Cloudflare answered at the edge and what reached our servers, bots vs browsers, the far
 * scraper countries, and beside them the beacon's human visits.
 */
class GetWebsitesEdgeTraffic
{
    use AsAction;

    public const array DAYS = [30, 60, 90, 365];

    /**
     * Visitor rows are pruned 30 days back by timestamp, so the oldest calendar day left is only
     * partly there: whole days start one day later.
     */
    private const int BEACON_WHOLE_DAYS = 29;

    /**
     * @return array<string, mixed>
     */
    public function handle(Group $group, User $user, ?string $selected, ?int $days): array
    {
        [$websites, $chosen, $selected] = $this->chosenWebsites($group, $user, $selected);
        [$from, $to, $days]             = $this->window($days);
        $beaconFrom                     = $this->beaconFrom($from);

        $daily = $this->edgeDays($chosen, $from, $to);
        foreach ($this->humanDays($chosen, $from, $beaconFrom, $to) as $date => $humans) {
            $daily[$date] = [...($daily[$date] ?? []), ...$humans];
        }
        ksort($daily);

        return [
            'selected'      => $selected,
            'days'          => $days,
            'days_options'  => self::DAYS,
            'options'       => $this->options($websites),
            'far_countries' => FetchWebsiteEdgeTimeSeries::FAR_COUNTRIES,
            'events'        => [
                ['date' => '2026-10-31', 'label' => __('Edge cache on (10 min)')],
                ['date' => '2026-11-01', 'label' => __('Edge cache 24 h for everyone')],
            ],
            'daily'         => collect($daily)->map(fn (array $day, string $date) => ['date' => $date, ...$day])->values()->all(),
            'countries'     => $this->countries($chosen, $beaconFrom, $to),
        ];
    }

    /**
     * The group's live websites the user may see, and those of the selection: 'all', 'org:<slug>' or
     * a website slug. An unknown selection falls back to all.
     *
     * @return array{0: Collection<int, Website>, 1: Collection<int, Website>, 2: string}
     */
    public function chosenWebsites(Group $group, User $user, ?string $selected): array
    {
        $websites = Website::where('group_id', $group->id)
            ->where('state', WebsiteStateEnum::LIVE)
            ->whereIn('organisation_id', $user->authorisedOrganisations()->pluck('organisations.id'))
            ->with('organisation:id,slug,name')
            ->orderBy('organisation_id')->orderBy('id')
            ->get(['id', 'slug', 'name', 'domain', 'organisation_id', 'shop_id']);

        $chosen = match (true) {
            str_starts_with((string) $selected, 'org:') => $websites->filter(fn (Website $website) => $website->organisation->slug === substr($selected, 4)),
            $selected && $selected !== 'all' => $websites->where('slug', $selected),
            default => $websites,
        };

        return $chosen->isEmpty() ? [$websites, $websites, 'all'] : [$websites, $chosen, $selected ?: 'all'];
    }

    /**
     * Whole UTC days up to yesterday, today being partial in every source. A single day, when
     * given, narrows the window to it.
     *
     * @return array{0: Carbon, 1: Carbon, 2: int}
     */
    public function window(?int $days, ?string $day = null): array
    {
        $days = in_array($days, self::DAYS, true) ? $days : self::DAYS[0];
        $from = now()->subDays($days)->startOfDay();
        $to   = now()->subDay()->endOfDay();

        $date = $day && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $day, $parts) && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? Carbon::parse($day) : null;
        if ($date && $date->between($from, $to)) {
            return [$date->copy()->startOfDay(), $date->copy()->endOfDay(), $days];
        }

        return [$from, $to, $days];
    }

    public function beaconFrom(Carbon $from): Carbon
    {
        return max($from, now()->subDays(self::BEACON_WHOLE_DAYS)->startOfDay());
    }

    /**
     * @return array<string, array<string, int|null>>
     */
    protected function edgeDays(Collection $websites, $from, $to): array
    {
        return DB::table('website_edge_time_series_records as records')
            ->join('website_edge_time_series as series', 'series.id', 'records.website_edge_time_series_id')
            ->whereIn('series.website_id', $websites->pluck('id'))
            ->where('series.frequency', 'daily')
            ->whereBetween('records.from', [$from, $to])
            ->groupBy('records.period')
            ->select(['records.period', ...array_map(fn (string $metric) => DB::raw("SUM(records.$metric)::bigint as $metric"), FetchWebsiteEdgeTimeSeries::METRICS), DB::raw('SUM(records.requests_octane)::bigint as requests_octane')])
            ->get()
            ->mapWithKeys(fn (object $row) => [$row->period => array_map(fn ($value) => $value === null ? null : (int) $value, array_diff_key((array) $row, ['period' => true]))])
            ->all();
    }

    /**
     * Human visits from our own beacon. The logged-in share needs the raw visitor rows, so it only
     * exists from $beaconFrom, the first whole day still kept.
     *
     * @return array<string, array<string, int|null>>
     */
    protected function humanDays(Collection $websites, $from, $beaconFrom, $to): array
    {
        $days = DB::table('website_time_series_records as records')
            ->join('website_time_series as series', 'series.id', 'records.website_time_series_id')
            ->whereIn('series.website_id', $websites->pluck('id'))
            ->where('series.frequency', 'daily')
            ->where('records.frequency', 'D')
            ->whereBetween('records.from', [$from, $to])
            ->groupBy('records.period')
            ->select('records.period', DB::raw('SUM(records.sessions)::int as sessions'), DB::raw('SUM(records.page_views)::int as page_views'))
            ->get()
            ->mapWithKeys(fn (object $row) => [$row->period => ['sessions' => (int) $row->sessions, 'page_views' => (int) $row->page_views, 'logged_in_sessions' => null]])
            ->all();

        DB::table('website_visitors')
            ->whereIn('website_id', $websites->pluck('id'))
            ->whereBetween('first_seen_at', [$beaconFrom, $to])
            ->groupBy(DB::raw('CAST(first_seen_at AS DATE)'))
            ->select(DB::raw('CAST(first_seen_at AS DATE) as date'), DB::raw('COUNT(*) as sessions'), DB::raw('COUNT(*) FILTER (WHERE web_user_id IS NOT NULL) as logged_in'))
            ->get()
            ->each(function (object $row) use (&$days) {
                $days[$row->date]['sessions']           = (int) $row->sessions;
                $days[$row->date]['logged_in_sessions'] = (int) $row->logged_in;
                $days[$row->date]['page_views'] ??= null;
            });

        return $days;
    }

    /**
     * @return array<int, array{code: string|null, sessions: int, logged_in_sessions: int}>
     */
    protected function countries(Collection $websites, $from, $to): array
    {
        return DB::table('website_visitors')
            ->whereIn('website_id', $websites->pluck('id'))
            ->whereBetween('first_seen_at', [$from, $to])
            ->groupBy('country_code')
            ->orderByDesc('sessions')
            ->limit(15)
            ->select('country_code as code', DB::raw('COUNT(*)::int as sessions'), DB::raw('COUNT(*) FILTER (WHERE web_user_id IS NOT NULL)::int as logged_in_sessions'))
            ->get()
            ->map(fn (object $row) => (array) $row)
            ->all();
    }

    /**
     * @return array<int, array{label: string, items: array<int, array{label: string, value: string}>}>
     */
    protected function options(Collection $websites): array
    {
        return [
            ['label' => __('Group'), 'items' => [['label' => __('All websites'), 'value' => 'all']]],
            [
                'label' => __('Organisations'),
                'items' => $websites->pluck('organisation')->unique('id')->map(fn ($organisation) => ['label' => $organisation->name, 'value' => 'org:'.$organisation->slug])->values()->all(),
            ],
            [
                'label' => __('Websites'),
                'items' => $websites->map(fn (Website $website) => ['label' => $website->domain, 'value' => $website->slug])->values()->all(),
            ],
        ];
    }
}
