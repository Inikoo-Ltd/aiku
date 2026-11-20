<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\SearchConsole;

use App\Actions\Web\Seo\GetSeoPeriods;
use App\Helpers\ComparePeriod;
use App\Models\Web\Website;
use App\Services\SearchConsole\SearchConsoleClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteSearchConsoleStats
{
    use AsObject;

    /**
     * The Google Search figures of the period and, when comparing, the same figures over the compared
     * period, its daily records moved onto the matching days of the period.
     */
    public function handle(Website $website, ?string $fromDate = null, ?string $toDate = null, ?ComparePeriod $compare = null): array
    {
        $isConnected = (bool) Arr::get($website->data, 'gcp.siteUrl');

        $latestStoredDay = DB::connection('aiku_no_sticky')->table('search_console_website_days')
            ->where('website_id', $website->id)
            ->max('date');

        $daily = $this->daily($website, $fromDate ? Carbon::parse($fromDate) : null, $toDate ? Carbon::parse($toDate) : null);

        $periods    = GetSeoPeriods::run($website, $fromDate, $toDate, $compare);
        $period     = $periods['search'];
        $comparison = null;

        if (isset($period['previous_from'])) {
            $from         = Carbon::parse($period['from']);
            $previousFrom = Carbon::parse($period['previous_from']);
            $previousTo   = Carbon::parse($period['previous_to']);

            $comparison = array_merge($this->summary($this->daily($website, $previousFrom, $previousTo)), [
                'from'  => $period['previous_from'],
                'to'    => $period['previous_to'],
                'label' => $periods['label'],
            ]);

            $comparison['daily'] = collect($comparison['daily'])
                ->map(fn (array $day) => array_merge($day, [
                    'day' => $from->copy()->addDays((int) $previousFrom->diffInDays(Carbon::parse($day['day'])))->toDateString(),
                ]))
                ->all();
        }

        return array_merge([
            'is_connected'          => $isConnected,
            'site_url'              => Arr::get($website->data, 'gcp.siteUrl'),
            'service_account_email' => $isConnected ? null : SearchConsoleClient::serviceAccountEmail($website),
            'latest_stored_day'     => $latestStoredDay,
        ], $this->summary($daily), [
            'comparison' => $comparison,
        ]);
    }

    private function daily(Website $website, ?Carbon $from, ?Carbon $to): Collection
    {
        return DB::connection('aiku_no_sticky')->table('search_console_website_days')
            ->where('website_id', $website->id)
            ->when($from, fn ($query) => $query->where('date', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->where('date', '<=', $to->toDateString()))
            ->groupBy('date')
            ->orderBy('date')
            ->select('date')
            ->selectRaw('SUM(clicks) as clicks')
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('SUM(position * impressions) as weighted_position')
            ->get();
    }

    /**
     * @return array{days_with_data: int, first_day: string|null, last_day: string|null, clicks: int, impressions: int, ctr: float, position: float|null, daily: array<int, array{day: string, clicks: int, impressions: int}>}
     */
    private function summary(Collection $daily): array
    {
        $clicks      = (int) $daily->sum('clicks');
        $impressions = (int) $daily->sum('impressions');

        return [
            'days_with_data' => $daily->count(),
            'first_day'      => $daily->first()?->date,
            'last_day'       => $daily->last()?->date,
            'clicks'         => $clicks,
            'impressions'    => $impressions,
            'ctr'            => $impressions > 0 ? round($clicks * 100 / $impressions, 2) : 0,
            'position'       => $impressions > 0 ? round($daily->sum('weighted_position') / $impressions, 1) : null,
            'daily'          => $daily->map(fn ($day) => [
                'day'         => $day->date,
                'clicks'      => (int) $day->clicks,
                'impressions' => (int) $day->impressions,
            ])->values()->all(),
        ];
    }
}
