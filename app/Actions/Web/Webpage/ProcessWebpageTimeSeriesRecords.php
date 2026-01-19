<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Traits\WithTimeSeriesRecordsGeneration;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateTimeSeriesNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Web\WebsiteConversionEvent\WebsiteConversionEventTypeEnum;
use App\Models\Web\Webpage;
use App\Models\Web\WebpageTimeSeries;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessWebpageTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use WithTimeSeriesRecordsGeneration;

    public function getJobUniqueId(int $webpageId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$webpageId:$frequency->value:$from:$to";
    }

    public function handle(int $webpageId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $webpage = Webpage::find($webpageId);
        if (!$webpage) {
            return;
        }

        $timeSeries = WebpageTimeSeries::where('webpage_id', $webpage->id)
            ->where('frequency', $frequency->value)->first();
        if (!$timeSeries) {
            $timeSeries = $webpage->timeSeries()->create([
                'frequency' => $frequency,
            ]);
        }

        if ($frequency === TimeSeriesFrequencyEnum::DAILY) {
            $this->processDailyTimeSeries($timeSeries, $from, $to);
        } else {
            $this->processAggregatedTimeSeries($timeSeries, $from, $to);
        }

        WebpageHydrateTimeSeriesNumberRecords::run($timeSeries->id);
    }

    protected function processDailyTimeSeries(WebpageTimeSeries $timeSeries, string $from, string $to): void
    {
        // 1. Get Page View Stats
        $pageViewStats = DB::table('website_page_views')
            ->where('view_date', '>=', $from)
            ->where('view_date', '<=', $to)
            ->where('webpage_id', $timeSeries->webpage_id)
            ->select(
                DB::raw('CAST(view_date AS DATE) as date'),
                DB::raw('COUNT(DISTINCT website_visitor_id) as visitors'),
                DB::raw('COUNT(id) as page_views'),
                DB::raw('AVG(duration_seconds) as avg_time_on_page')
            )
            ->groupBy(DB::raw('CAST(view_date AS DATE)'))
            ->get()
            ->keyBy('date');

        // 2. Get Conversion Stats (Add To Basket)
        $conversionStats = DB::table('website_conversion_events')
            ->where('event_date', '>=', $from)
            ->where('event_date', '<=', $to)
            ->where('webpage_id', $timeSeries->webpage_id)
            ->where('event_type', WebsiteConversionEventTypeEnum::ADD_TO_BASKET->value)
            ->select(
                DB::raw('CAST(event_date AS DATE) as date'),
                DB::raw('COUNT(id) as add_to_baskets')
            )
            ->groupBy(DB::raw('CAST(event_date AS DATE)'))
            ->get()
            ->keyBy('date');

        // 3. Merge and Process
        $dates = $pageViewStats->keys()->merge($conversionStats->keys())->unique();

        foreach ($dates as $date) {
            $periodFrom = Carbon::parse($date)->startOfDay();
            $periodTo   = Carbon::parse($date)->endOfDay();
            $period     = Carbon::parse($date)->format('Y-m-d');

            $views = $pageViewStats->get($date);
            $conversions = $conversionStats->get($date);

            $visitors = $views->visitors ?? 0;
            $pageViews = $views->page_views ?? 0;
            $avgTimeOnPage = $views->avg_time_on_page ?? 0;
            $addToBaskets = $conversions->add_to_baskets ?? 0;

            // Calculate Conversion Rate (based on visitors)
            $conversionRate = $pageViews > 0 ? ($addToBaskets / $pageViews) * 100 : 0;

            if ($conversionRate > 999.99) {
                $conversionRate = 999.99;
            }

            $timeSeries->records()->updateOrCreate(
                [
                    'webpage_time_series_id' => $timeSeries->id,
                    'period'                 => $period,
                    'frequency'              => $timeSeries->frequency->singleLetter()
                ],
                [
                    'from'             => $periodFrom,
                    'to'               => $periodTo,
                    'visitors'         => $visitors,
                    'page_views'       => $pageViews,
                    'avg_time_on_page' => round($avgTimeOnPage),
                    'add_to_baskets'   => $addToBaskets,
                    'conversion_rate'  => round($conversionRate, 2),
                ]
            );
        }
    }

    protected function processAggregatedTimeSeries(WebpageTimeSeries $timeSeries, string $from, string $to): void
    {
        // Find the Daily Time Series for this webpage
        $dailyTimeSeries = WebpageTimeSeries::where('webpage_id', $timeSeries->webpage_id)
            ->where('frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->first();

        if (!$dailyTimeSeries) {
            return;
        }

        // Aggregate from Daily Records
        $query = DB::table('webpage_time_series_records')
            ->where('webpage_time_series_id', $dailyTimeSeries->id)
            ->where('from', '>=', $from)
            ->where('to', '<=', $to);

        $selects = [
            DB::raw('SUM(visitors) as visitors'),
            DB::raw('SUM(page_views) as page_views'),
            DB::raw('SUM(add_to_baskets) as add_to_baskets'),
            // Weighted Average for duration: Sum(avg_time * page_views) / Sum(page_views)
            // Assuming avg_time_on_page is per page view? Or per visitor?
            // "avg_time_on_page" usually means per page view (or session).
            // In daily calc: AVG(duration_seconds) from page_views table.
            // So to aggregate: SUM(avg_time_on_page * page_views) / SUM(page_views)
            DB::raw('SUM(avg_time_on_page * page_views) as total_duration'),
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

            $avgTimeOnPage = $result->page_views > 0 ? $result->total_duration / $result->page_views : 0;
            // Recalculate conversion rate for the aggregated period
            // Sum of visitors vs sum of add to baskets?
            // Sum(visitors) is not Unique Visitors for the month, but it is the sum of Daily Unique Visitors.
            // Sum(add_to_baskets) is correct total.
            // So Conversion Rate = (Total Add / Total Daily Visitors) * 100. This is an approximation of "Average Daily Conversion Rate" or just "Conversion Rate based on Visits".
            $conversionRate = $result->page_views > 0 ? ($result->add_to_baskets / $result->visitors) * 100 : 0;

            if ($conversionRate > 999.99) {
                $conversionRate = 999.99;
            }

            $timeSeries->records()->updateOrCreate(
                [
                    'webpage_time_series_id' => $timeSeries->id,
                    'period'                 => $period,
                    'frequency'              => $timeSeries->frequency->singleLetter()
                ],
                [
                    'from'             => $periodFrom,
                    'to'               => $periodTo,
                    'visitors'         => $result->visitors,
                    'page_views'       => $result->page_views,
                    'avg_time_on_page' => round($avgTimeOnPage),
                    'add_to_baskets'   => $result->add_to_baskets,
                    'conversion_rate'  => round($conversionRate, 2),
                ]
            );
        }
    }
}
