<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 06 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Web\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsitePerformanceStats
{
    use AsObject;

    /**
     * @return array{days_with_data: int, first_day: string|null, last_day: string|null, visitors: int, sessions: int, page_views: int, pages_per_session: float, avg_session_duration: int, bounce_rate: float, new_visitors: int, returning_visitors: int, sessions_desktop: int, sessions_mobile: int, sessions_tablet: int, daily: array<int, array{day: string, visitors: int, page_views: int}>}|null
     */
    public function handle(Website $website, ?string $fromDate = null, ?string $toDate = null): ?array
    {
        $dailyTimeSeries = $website->timeSeries()
            ->where('frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->first();

        if (!$dailyTimeSeries) {
            return null;
        }

        $records = DB::connection('aiku_no_sticky')->table('website_time_series_records')
            ->where('website_time_series_id', $dailyTimeSeries->id)
            ->where('frequency', TimeSeriesFrequencyEnum::DAILY->singleLetter())
            ->when($fromDate, fn ($query) => $query->where('period', '>=', Carbon::parse($fromDate)->toDateString()))
            ->when($toDate, fn ($query) => $query->where('period', '<=', Carbon::parse($toDate)->toDateString()));

        $daily = (clone $records)
            ->orderBy('period')
            ->get(['period', 'visitors', 'page_views'])
            ->map(fn ($record) => [
                'day'        => $record->period,
                'visitors'   => (int) $record->visitors,
                'page_views' => (int) $record->page_views,
            ])
            ->all();

        $totals = $records
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
                COALESCE(SUM(visitors_tablet), 0) as sessions_tablet
            ')
            ->first();

        $sessions = (int) $totals->sessions;

        return [
            'days_with_data'       => (int) $totals->days_with_data,
            'first_day'            => $totals->first_day,
            'last_day'             => $totals->last_day,
            'visitors'             => (int) $totals->visitors,
            'sessions'             => $sessions,
            'page_views'           => (int) $totals->page_views,
            'pages_per_session'    => $sessions > 0 ? round($totals->page_views / $sessions, 2) : 0,
            'avg_session_duration' => $sessions > 0 ? (int) round($totals->total_duration / $sessions) : 0,
            'bounce_rate'          => $sessions > 0 ? round(($totals->total_bounces / $sessions) * 100, 2) : 0,
            'new_visitors'         => (int) $totals->new_visitors,
            'returning_visitors'   => (int) $totals->returning_visitors,
            'sessions_desktop'     => (int) $totals->sessions_desktop,
            'sessions_mobile'      => (int) $totals->sessions_mobile,
            'sessions_tablet'      => (int) $totals->sessions_tablet,
            'daily'                => $daily,
        ];
    }
}
