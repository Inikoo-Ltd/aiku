<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Web\Webpage\Hydrators\WebpageHydrateTimeSeriesNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Web\WebsiteConversionEvent\WebsiteConversionEventTypeEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Web\Webpage;
use App\Models\Web\WebpageTimeSeries;
use App\Traits\BuildsAggregatedTimeSeriesQuery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessWebpageTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsAggregatedTimeSeriesQuery;

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

        $timeSeries = WebpageTimeSeries::where('webpage_id', $webpage->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $webpage->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        WebpageHydrateTimeSeriesNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(WebpageTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];

        $results = $timeSeries->frequency === TimeSeriesFrequencyEnum::DAILY
            ? $this->fetchDailyResults($timeSeries, $from, $to)
            : $this->fetchAggregatedResults($timeSeries, $from, $to);

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            if ($timeSeries->frequency === TimeSeriesFrequencyEnum::DAILY) {
                $avgTimeOnPage  = round($result->avg_time_on_page);
                $conversionRate = $result->page_views > 0 ? ($result->add_to_baskets / $result->page_views) * 100 : 0;
            } else {
                $avgTimeOnPage  = $result->page_views > 0 ? round($result->total_duration / $result->page_views) : 0;
                $conversionRate = $result->visitors > 0 ? ($result->add_to_baskets / $result->visitors) * 100 : 0;
            }

            if ($conversionRate > 999.99) {
                $conversionRate = 999.99;
            }

            $timeSeries->records()->updateOrCreate(
                [
                    'webpage_time_series_id' => $timeSeries->id,
                    'period'                 => $period,
                    'frequency'              => $timeSeries->frequency->singleLetter(),
                ],
                [
                    'from'             => $periodFrom,
                    'to'               => $periodTo,
                    'visitors'         => $result->visitors,
                    'page_views'       => $result->page_views,
                    'avg_time_on_page' => $avgTimeOnPage,
                    'add_to_baskets'   => $result->add_to_baskets,
                    'conversion_rate'  => round($conversionRate, 2),
                ]
            );

            $processedPeriods[] = $period;
        }

        $this->processPeriodsWithoutData($timeSeries, $from, $to, $processedPeriods);
    }

    protected function fetchDailyResults(WebpageTimeSeries $timeSeries, string $from, string $to): Collection
    {
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

        return $pageViewStats->keys()->merge($conversionStats->keys())->unique()->map(fn ($date) => (object) [
            'date'             => $date,
            'visitors'         => $pageViewStats->get($date)?->visitors ?? 0,
            'page_views'       => $pageViewStats->get($date)?->page_views ?? 0,
            'avg_time_on_page' => $pageViewStats->get($date)?->avg_time_on_page ?? 0,
            'add_to_baskets'   => $conversionStats->get($date)?->add_to_baskets ?? 0,
        ]);
    }

    protected function fetchAggregatedResults(WebpageTimeSeries $timeSeries, string $from, string $to): Collection
    {
        $dailyTimeSeries = WebpageTimeSeries::where('webpage_id', $timeSeries->webpage_id)->where('frequency', TimeSeriesFrequencyEnum::DAILY->value)->first();

        if (!$dailyTimeSeries) {
            return collect();
        }

        $selects = [
            DB::raw('SUM(visitors) as visitors'),
            DB::raw('SUM(page_views) as page_views'),
            DB::raw('SUM(add_to_baskets) as add_to_baskets'),
            DB::raw('SUM(avg_time_on_page * page_views) as total_duration'),
        ];

        $query = DB::table('webpage_time_series_records')
            ->where('webpage_time_series_id', $dailyTimeSeries->id)
            ->where('from', '>=', $from)
            ->where('to', '<=', $to);

        return $this->applyAggregatedFrequencyGrouping($query, $timeSeries->frequency, $selects)->get();
    }

    protected function processPeriodsWithoutData(WebpageTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): void
    {
        $emptyPeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($emptyPeriods as $periodData) {
            $timeSeries->records()->updateOrCreate(
                [
                    'webpage_time_series_id' => $timeSeries->id,
                    'period'                 => $periodData['period'],
                    'frequency'              => $timeSeries->frequency->singleLetter(),
                ],
                [
                    'from'             => $periodData['from'],
                    'to'               => $periodData['to'],
                    'visitors'         => 0,
                    'page_views'       => 0,
                    'avg_time_on_page' => 0,
                    'add_to_baskets'   => 0,
                    'conversion_rate'  => 0,
                ]
            );
        }
    }
}
