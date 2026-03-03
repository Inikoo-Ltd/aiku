<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website;

use App\Actions\Web\Website\Hydrators\WebsiteHydrateTimeSeriesNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Web\Website;
use App\Models\Web\WebsiteTimeSeries;
use App\Traits\BuildsAggregatedTimeSeriesQuery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessWebsiteTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsAggregatedTimeSeriesQuery;

    public function getJobUniqueId(int $websiteId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$websiteId:$frequency->value:$from:$to";
    }

    public function handle(int $websiteId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $website = Website::find($websiteId);

        if (!$website) {
            return;
        }

        $timeSeries = WebsiteTimeSeries::where('website_id', $website->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $website->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        WebsiteHydrateTimeSeriesNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(WebsiteTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];

        $results = $timeSeries->frequency === TimeSeriesFrequencyEnum::DAILY
            ? $this->fetchDailyResults($timeSeries, $from, $to)
            : $this->fetchAggregatedResults($timeSeries, $from, $to);

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            if ($timeSeries->frequency === TimeSeriesFrequencyEnum::DAILY) {
                $avgSessionDuration = round($result->avg_session_duration);
                $bounceRate         = $result->sessions > 0 ? round(($result->bounces / $result->sessions) * 100, 2) : 0;
                $pagesPerSession    = round($result->pages_per_session, 2);
                $returningVisitors  = max(0, $result->visitors - $result->new_visitors);
            } else {
                $avgSessionDuration = $result->sessions > 0 ? round($result->total_duration / $result->sessions) : 0;
                $bounceRate         = $result->sessions > 0 ? round(($result->total_bounces / $result->sessions) * 100, 2) : 0;
                $pagesPerSession    = $result->sessions > 0 ? round($result->page_views / $result->sessions, 2) : 0;
                $returningVisitors  = $result->returning_visitors;
            }

            $timeSeries->records()->updateOrCreate(
                [
                    'website_time_series_id' => $timeSeries->id,
                    'period'                 => $period,
                    'frequency'              => $timeSeries->frequency->singleLetter(),
                ],
                [
                    'from'                 => $periodFrom,
                    'to'                   => $periodTo,
                    'visitors'             => $result->visitors,
                    'sessions'             => $result->sessions,
                    'page_views'           => $result->page_views,
                    'avg_session_duration' => $avgSessionDuration,
                    'bounce_rate'          => $bounceRate,
                    'pages_per_session'    => $pagesPerSession,
                    'new_visitors'         => $result->new_visitors,
                    'returning_visitors'   => $returningVisitors,
                    'visitors_desktop'     => $result->visitors_desktop,
                    'visitors_mobile'      => $result->visitors_mobile,
                    'visitors_tablet'      => $result->visitors_tablet,
                ]
            );

            $processedPeriods[] = $period;
        }

        $this->processPeriodsWithoutData($timeSeries, $from, $to, $processedPeriods);
    }

    protected function fetchDailyResults(WebsiteTimeSeries $timeSeries, string $from, string $to): Collection
    {
        return DB::table('website_visitors')
            ->where('first_seen_at', '>=', $from)
            ->where('first_seen_at', '<=', $to)
            ->where('website_id', $timeSeries->website_id)
            ->select(
                DB::raw('CAST(first_seen_at AS DATE) as date'),
                DB::raw('COUNT(DISTINCT visitor_hash) as visitors'),
                DB::raw('COUNT(id) as sessions'),
                DB::raw('SUM(page_views) as page_views'),
                DB::raw('AVG(duration_seconds) as avg_session_duration'),
                DB::raw('SUM(CASE WHEN is_bounce = true THEN 1 ELSE 0 END) as bounces'),
                DB::raw('AVG(page_views) as pages_per_session'),
                DB::raw('SUM(CASE WHEN is_new_visitor = true THEN 1 ELSE 0 END) as new_visitors'),
                DB::raw("SUM(CASE WHEN LOWER(device_type) = 'desktop' THEN 1 ELSE 0 END) as visitors_desktop"),
                DB::raw("SUM(CASE WHEN LOWER(device_type) = 'mobile' THEN 1 ELSE 0 END) as visitors_mobile"),
                DB::raw("SUM(CASE WHEN LOWER(device_type) = 'tablet' THEN 1 ELSE 0 END) as visitors_tablet")
            )
            ->groupBy(DB::raw('CAST(first_seen_at AS DATE)'))
            ->get();
    }

    protected function fetchAggregatedResults(WebsiteTimeSeries $timeSeries, string $from, string $to): Collection
    {
        $dailyTimeSeries = WebsiteTimeSeries::where('website_id', $timeSeries->website_id)->where('frequency', TimeSeriesFrequencyEnum::DAILY->value)->first();

        if (!$dailyTimeSeries) {
            return collect();
        }

        $selects = [
            DB::raw('SUM(visitors) as visitors'),
            DB::raw('SUM(sessions) as sessions'),
            DB::raw('SUM(page_views) as page_views'),
            DB::raw('SUM(avg_session_duration * sessions) as total_duration'),
            DB::raw('SUM((bounce_rate / 100) * sessions) as total_bounces'),
            DB::raw('SUM(new_visitors) as new_visitors'),
            DB::raw('SUM(returning_visitors) as returning_visitors'),
            DB::raw('SUM(visitors_desktop) as visitors_desktop'),
            DB::raw('SUM(visitors_mobile) as visitors_mobile'),
            DB::raw('SUM(visitors_tablet) as visitors_tablet'),
        ];

        $query = DB::table('website_time_series_records')
            ->where('website_time_series_id', $dailyTimeSeries->id)
            ->where('from', '>=', $from)
            ->where('to', '<=', $to);

        return $this->applyAggregatedFrequencyGrouping($query, $timeSeries->frequency, $selects)->get();
    }

    protected function processPeriodsWithoutData(WebsiteTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): void
    {
        $emptyPeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($emptyPeriods as $periodData) {
            $timeSeries->records()->updateOrCreate(
                [
                    'website_time_series_id' => $timeSeries->id,
                    'period'                 => $periodData['period'],
                    'frequency'              => $timeSeries->frequency->singleLetter(),
                ],
                [
                    'from'                 => $periodData['from'],
                    'to'                   => $periodData['to'],
                    'visitors'             => 0,
                    'sessions'             => 0,
                    'page_views'           => 0,
                    'avg_session_duration' => 0,
                    'bounce_rate'          => 0,
                    'pages_per_session'    => 0,
                    'new_visitors'         => 0,
                    'returning_visitors'   => 0,
                    'visitors_desktop'     => 0,
                    'visitors_mobile'      => 0,
                    'visitors_tablet'      => 0,
                ]
            );
        }
    }
}
