<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website;

use App\Actions\Traits\WithTimeSeriesRecordsGeneration;
use App\Actions\Web\Website\Hydrators\WebsiteHydrateTimeSeriesNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Web\Website;
use App\Models\Web\WebsiteTimeSeries;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessWebsiteTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use WithTimeSeriesRecordsGeneration;

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

        $timeSeries = WebsiteTimeSeries::where('website_id', $website->id)
            ->where('frequency', $frequency->value)->first();
        if (!$timeSeries) {
            $timeSeries = $website->timeSeries()->create([
                'frequency' => $frequency,
            ]);
        }

        if ($frequency === TimeSeriesFrequencyEnum::DAILY) {
            $this->processDailyTimeSeries($timeSeries, $from, $to);
        } else {
            $this->processAggregatedTimeSeries($timeSeries, $from, $to);
        }

        WebsiteHydrateTimeSeriesNumberRecords::run($timeSeries->id);
    }

    protected function processDailyTimeSeries(WebsiteTimeSeries $timeSeries, string $from, string $to): void
    {
        $results = DB::table('website_visitors')
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

        foreach ($results as $result) {
            $periodFrom = Carbon::parse($result->date)->startOfDay();
            $periodTo   = Carbon::parse($result->date)->endOfDay();
            $period     = Carbon::parse($result->date)->format('Y-m-d');

            $bounceRate = $result->sessions > 0 ? round(($result->bounces / $result->sessions) * 100, 2) : 0;
            $returningVisitors = max(0, $result->visitors - $result->new_visitors);

            $timeSeries->records()->updateOrCreate(
                [
                    'website_time_series_id' => $timeSeries->id,
                    'period'                 => $period,
                    'frequency'              => $timeSeries->frequency->singleLetter()
                ],
                [
                    'from'                 => $periodFrom,
                    'to'                   => $periodTo,
                    'visitors'             => $result->visitors,
                    'sessions'             => $result->sessions,
                    'page_views'           => $result->page_views,
                    'avg_session_duration' => round($result->avg_session_duration),
                    'bounce_rate'          => $bounceRate,
                    'pages_per_session'    => round($result->pages_per_session, 2),
                    'new_visitors'         => $result->new_visitors,
                    'returning_visitors'   => $returningVisitors,
                    'visitors_desktop'     => $result->visitors_desktop,
                    'visitors_mobile'      => $result->visitors_mobile,
                    'visitors_tablet'      => $result->visitors_tablet,
                ]
            );
        }
    }

    protected function processAggregatedTimeSeries(WebsiteTimeSeries $timeSeries, string $from, string $to): void
    {
        // Find the Daily Time Series for this website
        $dailyTimeSeries = WebsiteTimeSeries::where('website_id', $timeSeries->website_id)
            ->where('frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->first();

        if (!$dailyTimeSeries) {
            return;
        }

        // Aggregate from Daily Records
        $query = DB::table('website_time_series_records')
            ->where('website_time_series_id', $dailyTimeSeries->id)
            ->where('from', '>=', $from)
            ->where('to', '<=', $to);

        $selects = [
            DB::raw('SUM(visitors) as visitors'),
            DB::raw('SUM(sessions) as sessions'),
            DB::raw('SUM(page_views) as page_views'),
            // Weighted Average for duration: Sum(duration * sessions) / Sum(sessions)
            // Since we stored average, we approximate total duration as avg_session_duration * sessions
            DB::raw('SUM(avg_session_duration * sessions) as total_duration'),
            // Reconstruct bounces: (bounce_rate / 100) * sessions
            DB::raw('SUM((bounce_rate / 100) * sessions) as total_bounces'),
            DB::raw('SUM(new_visitors) as new_visitors'),
            DB::raw('SUM(returning_visitors) as returning_visitors'),
            DB::raw('SUM(visitors_desktop) as visitors_desktop'),
            DB::raw('SUM(visitors_mobile) as visitors_mobile'),
            DB::raw('SUM(visitors_tablet) as visitors_tablet'),
        ];

        if ($timeSeries->frequency == TimeSeriesFrequencyEnum::YEARLY) {
            $query->select(array_merge([
                DB::raw('EXTRACT(YEAR FROM "from") as year'),
            ], $selects))->groupBy(DB::raw('EXTRACT(YEAR FROM "from")'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::QUARTERLY) {
            $query->select(array_merge([
                DB::raw('EXTRACT(YEAR FROM "from") as year'),
                DB::raw('EXTRACT(QUARTER FROM "from") as quarter'),
            ], $selects))->groupBy(DB::raw('EXTRACT(YEAR FROM "from")'), DB::raw('EXTRACT(QUARTER FROM "from")'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::MONTHLY) {
            $query->select(array_merge([
                DB::raw('EXTRACT(YEAR FROM "from") as year'),
                DB::raw('EXTRACT(MONTH FROM "from") as month'),
            ], $selects))->groupBy(DB::raw('EXTRACT(YEAR FROM "from")'), DB::raw('EXTRACT(MONTH FROM "from")'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::WEEKLY) {
            $query->select(array_merge([
                DB::raw('EXTRACT(YEAR FROM "from") as year'),
                DB::raw('EXTRACT(WEEK FROM "from") as week'),
            ], $selects))->groupBy(DB::raw('EXTRACT(YEAR FROM "from")'), DB::raw('EXTRACT(WEEK FROM "from")'));
        }

        $results = $query->get();

        foreach ($results as $result) {
            if ($timeSeries->frequency == TimeSeriesFrequencyEnum::QUARTERLY) {
                $periodFrom = Carbon::create((int)$result->year, ((int)$result->quarter - 1) * 3 + 1)->startOfQuarter();
                $periodTo   = Carbon::create((int)$result->year, ((int)$result->quarter - 1) * 3 + 1)->endOfQuarter();
                $period     = $result->year.' Q'.$result->quarter;
            } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::MONTHLY) {
                $periodFrom = Carbon::create((int)$result->year, (int)$result->month)->startOfMonth();
                $periodTo   = Carbon::create((int)$result->year, (int)$result->month)->endOfMonth();
                $period     = $result->year.'-'.str_pad($result->month, 2, '0', STR_PAD_LEFT);
            } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::WEEKLY) {
                $periodFrom = Carbon::create((int)$result->year)->week((int)$result->week)->startOfWeek();
                $periodTo   = Carbon::create((int)$result->year)->week((int)$result->week)->endOfWeek();
                $period     = $result->year.' W'.str_pad($result->week, 2, '0', STR_PAD_LEFT);
            } else {
                $periodFrom = Carbon::parse((int)$result->year.'-01-01');
                $periodTo   = Carbon::parse((int)$result->year.'-12-31');
                $period     = $result->year;
            }

            $avgSessionDuration = $result->sessions > 0 ? $result->total_duration / $result->sessions : 0;
            $bounceRate = $result->sessions > 0 ? ($result->total_bounces / $result->sessions) * 100 : 0;
            $pagesPerSession = $result->sessions > 0 ? $result->page_views / $result->sessions : 0;

            $timeSeries->records()->updateOrCreate(
                [
                    'website_time_series_id' => $timeSeries->id,
                    'period'                 => $period,
                    'frequency'              => $timeSeries->frequency->singleLetter()
                ],
                [
                    'from'                 => $periodFrom,
                    'to'                   => $periodTo,
                    'visitors'             => $result->visitors,
                    'sessions'             => $result->sessions,
                    'page_views'           => $result->page_views,
                    'avg_session_duration' => round($avgSessionDuration),
                    'bounce_rate'          => round($bounceRate, 2),
                    'pages_per_session'    => round($pagesPerSession, 2),
                    'new_visitors'         => $result->new_visitors,
                    'returning_visitors'   => $result->returning_visitors,
                    'visitors_desktop'     => $result->visitors_desktop,
                    'visitors_mobile'      => $result->visitors_mobile,
                    'visitors_tablet'      => $result->visitors_tablet,
                ]
            );
        }
    }
}
