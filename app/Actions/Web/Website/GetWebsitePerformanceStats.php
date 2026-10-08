<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 06 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Web\Website;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsitePerformanceStats
{
    use AsObject;

    /**
     * @return array{days_with_data: int, first_day: string|null, last_day: string|null, visitors: int, sessions: int, page_views: int, pages_per_session: float, avg_session_duration: int, bounce_rate: float, new_visitors: int, returning_visitors: int, sessions_desktop: int, sessions_mobile: int, sessions_tablet: int, add_to_baskets: int, checkouts: int, purchases: int, revenue: float, conversion_rate: float, average_order_value: float, currency_code: string|null, conversions_tracked_since: string|null, comparisons: array<string, array{from: string, to: string, days_with_data: int, sessions: int, add_to_baskets: int, checkouts: int, purchases: int, revenue: float, conversion_rate: float}>, daily: array<int, array{day: string, visitors: int, page_views: int, add_to_baskets: int, checkouts: int, purchases: int, revenue: float}>}|null
     */
    public function handle(Website $website, ?string $fromDate = null, ?string $toDate = null): ?array
    {
        $dailyTimeSeries = $website->timeSeries()
            ->where('frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->first();

        if (!$dailyTimeSeries) {
            return null;
        }

        $from = $fromDate ? Carbon::parse($fromDate)->startOfDay() : null;
        $to   = $toDate ? Carbon::parse($toDate)->startOfDay() : null;

        $daily = $this->dailyRecords($dailyTimeSeries->id, $from, $to)
            ->orderBy('period')
            ->get(['period', 'visitors', 'page_views', 'add_to_baskets', 'checkouts', 'purchases', 'revenue'])
            ->map(fn ($record) => [
                'day'            => $record->period,
                'visitors'       => (int) $record->visitors,
                'page_views'     => (int) $record->page_views,
                'add_to_baskets' => (int) $record->add_to_baskets,
                'checkouts'      => (int) $record->checkouts,
                'purchases'      => (int) $record->purchases,
                'revenue'        => (float) $record->revenue,
            ])
            ->all();

        $totals = $this->totals($dailyTimeSeries->id, $from, $to);

        $comparisons = [];

        if ($from && $to) {
            $length = (int) $from->diffInDays($to);

            $comparisons['previous_period'] = $this->comparisonTotals(
                $dailyTimeSeries->id,
                $from->copy()->subDays($length + 1),
                $from->copy()->subDay()
            );

            $comparisons['previous_year'] = $this->comparisonTotals(
                $dailyTimeSeries->id,
                $from->copy()->subYear(),
                $to->copy()->subYear()
            );
        }

        $conversionsTrackedSince = DB::connection('aiku_no_sticky')->table('website_time_series_records')
            ->where('website_time_series_id', $dailyTimeSeries->id)
            ->where('frequency', TimeSeriesFrequencyEnum::DAILY->singleLetter())
            ->where(fn ($query) => $query->where('checkouts', '>', 0)->orWhere('purchases', '>', 0))
            ->min('period');

        $sessions  = (int) $totals->sessions;
        $purchases = (int) $totals->purchases;
        $revenue   = (float) $totals->revenue;

        return [
            'days_with_data'            => (int) $totals->days_with_data,
            'first_day'                 => $totals->first_day,
            'last_day'                  => $totals->last_day,
            'visitors'                  => (int) $totals->visitors,
            'sessions'                  => $sessions,
            'page_views'                => (int) $totals->page_views,
            'pages_per_session'         => $sessions > 0 ? round($totals->page_views / $sessions, 2) : 0,
            'avg_session_duration'      => $sessions > 0 ? (int) round($totals->total_duration / $sessions) : 0,
            'bounce_rate'               => $sessions > 0 ? round(($totals->total_bounces / $sessions) * 100, 2) : 0,
            'new_visitors'              => (int) $totals->new_visitors,
            'returning_visitors'        => (int) $totals->returning_visitors,
            'sessions_desktop'          => (int) $totals->sessions_desktop,
            'sessions_mobile'           => (int) $totals->sessions_mobile,
            'sessions_tablet'           => (int) $totals->sessions_tablet,
            'add_to_baskets'            => (int) $totals->add_to_baskets,
            'checkouts'                 => (int) $totals->checkouts,
            'purchases'                 => $purchases,
            'revenue'                   => $revenue,
            'conversion_rate'           => $this->conversionRate($purchases, $sessions),
            'average_order_value'       => $purchases > 0 ? round($revenue / $purchases, 2) : 0,
            'currency_code'             => $website->shop->currency?->code,
            'conversions_tracked_since' => $conversionsTrackedSince,
            'comparisons'               => $comparisons,
            'daily'                     => $daily,
        ];
    }

    private function dailyRecords(int $timeSeriesId, ?Carbon $from, ?Carbon $to): Builder
    {
        return DB::connection('aiku_no_sticky')->table('website_time_series_records')
            ->where('website_time_series_id', $timeSeriesId)
            ->where('frequency', TimeSeriesFrequencyEnum::DAILY->singleLetter())
            ->when($from, fn ($query) => $query->where('period', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->where('period', '<=', $to->toDateString()));
    }

    private function totals(int $timeSeriesId, ?Carbon $from, ?Carbon $to): object
    {
        return $this->dailyRecords($timeSeriesId, $from, $to)
            ->selectRaw('
                COUNT(*) as days_with_data,
                MIN(period) as first_day,
                MAX(period) as last_day,
                COALESCE(SUM(visitors), 0) as visitors,
                COALESCE(SUM(sessions), 0) as sessions,
                COALESCE(SUM(page_views), 0) as page_views,
                COALESCE(SUM(avg_session_duration * sessions), 0) as total_duration,
                COALESCE(SUM((bounce_rate / 100) * sessions), 0) as total_bounces,
                COALESCE(SUM(new_visitors), 0) as new_visitors,
                COALESCE(SUM(returning_visitors), 0) as returning_visitors,
                COALESCE(SUM(visitors_desktop), 0) as sessions_desktop,
                COALESCE(SUM(visitors_mobile), 0) as sessions_mobile,
                COALESCE(SUM(visitors_tablet), 0) as sessions_tablet,
                COALESCE(SUM(add_to_baskets), 0) as add_to_baskets,
                COALESCE(SUM(checkouts), 0) as checkouts,
                COALESCE(SUM(purchases), 0) as purchases,
                COALESCE(SUM(revenue), 0) as revenue
            ')
            ->first();
    }

    /**
     * @return array{from: string, to: string, days_with_data: int, sessions: int, add_to_baskets: int, checkouts: int, purchases: int, revenue: float, conversion_rate: float}
     */
    private function comparisonTotals(int $timeSeriesId, Carbon $from, Carbon $to): array
    {
        $totals = $this->totals($timeSeriesId, $from, $to);

        return [
            'from'           => $from->toDateString(),
            'to'             => $to->toDateString(),
            'days_with_data' => (int) $totals->days_with_data,
            'sessions'       => (int) $totals->sessions,
            'add_to_baskets' => (int) $totals->add_to_baskets,
            'checkouts'      => (int) $totals->checkouts,
            'purchases'      => (int) $totals->purchases,
            'revenue'        => (float) $totals->revenue,
            'conversion_rate' => $this->conversionRate((int) $totals->purchases, (int) $totals->sessions),
        ];
    }

    private function conversionRate(int $purchases, int $sessions): float
    {
        return $sessions > 0 ? round(($purchases / $sessions) * 100, 2) : 0;
    }
}
