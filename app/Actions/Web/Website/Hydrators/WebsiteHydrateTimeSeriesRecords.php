<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website\Hydrators;

use App\Actions\Traits\WithTimeSeriesRecordsGeneration;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Web\WebsiteTimeSeries;
use App\Models\Web\WebsiteVisitor;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WebsiteHydrateTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use WithTimeSeriesRecordsGeneration;

    public function getJobUniqueId(int $timeSeriesId, string $from, string $to): string
    {
        return "hydrate-website-time-series-records:{$timeSeriesId}:{$from}:{$to}";
    }

    public function handle(int $timeSeriesId, Carbon $from, Carbon $to): int
    {
        $timeSeries = WebsiteTimeSeries::find($timeSeriesId);

        if (!$timeSeries) {
            return 0;
        }

        $website = $timeSeries->website;
        $frequency = $timeSeries->frequency;

        $periods = $this->generatePeriods($from, $to, $frequency);
        $recordsCreated = 0;

        foreach ($periods as $period) {
            if ($frequency === TimeSeriesFrequencyEnum::DAILY) {
                $data = $this->aggregateFromRawData($website->id, $period['from'], $period['to']);
            } else {
                $data = $this->aggregateFromLowerFrequency($website->id, $frequency, $period['from'], $period['to']);
            }

            $record = $timeSeries->records()->updateOrCreate(
                [
                    'from' => $period['from'],
                    'to' => $period['to'],
                ],
                $data
            );

            if ($record->wasRecentlyCreated) {
                $recordsCreated++;
            }
        }

        if ($recordsCreated > 0) {
            WebsiteHydrateTimeSeriesNumberRecords::dispatch($timeSeries->id);
        }

        return $recordsCreated;
    }

    protected function aggregateFromRawData(int $websiteId, Carbon $from, Carbon $to): array
    {
        $visitors = WebsiteVisitor::where('website_id', $websiteId)
            ->whereBetween('first_seen_at', [$from, $to])
            ->get();

        $totalSessions = $visitors->count();
        $uniqueVisitors = $visitors->unique('visitor_hash')->count();
        $newVisitors = $visitors->where('is_new_visitor', true)->count();
        $returningVisitors = $uniqueVisitors - $newVisitors;

        $pageViews = $visitors->sum('page_views');
        $totalDuration = $visitors->sum('duration_seconds');
        $bounces = $visitors->where('is_bounce', true)->count();

        $deviceCounts = $visitors->countBy('device_type');

        $avgSessionDuration = $totalSessions > 0 ? round($totalDuration / $totalSessions) : 0;
        $bounceRate = $totalSessions > 0 ? round(($bounces / $totalSessions) * 100, 2) : 0;
        $pagesPerSession = $totalSessions > 0 ? round($pageViews / $totalSessions, 2) : 0;

        return [
            'visitors' => $uniqueVisitors,
            'sessions' => $totalSessions,
            'page_views' => $pageViews,
            'avg_session_duration' => $avgSessionDuration,
            'bounce_rate' => $bounceRate,
            'pages_per_session' => $pagesPerSession,
            'new_visitors' => $newVisitors,
            'returning_visitors' => max(0, $returningVisitors),
            'visitors_desktop' => $deviceCounts->get('desktop', 0),
            'visitors_mobile' => $deviceCounts->get('mobile', 0),
            'visitors_tablet' => $deviceCounts->get('tablet', 0),
        ];
    }

    protected function aggregateFromLowerFrequency(int $websiteId, TimeSeriesFrequencyEnum $frequency, Carbon $from, Carbon $to): array
    {
        $lowerFrequency = $this->getLowerFrequency($frequency);

        $lowerTimeSeries = WebsiteTimeSeries::where('website_id', $websiteId)
            ->where('frequency', $lowerFrequency)
            ->first();

        if (!$lowerTimeSeries) {
            return $this->getEmptyData();
        }

        $records = $lowerTimeSeries->records()
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('from', [$from, $to])
                    ->orWhereBetween('to', [$from, $to])
                    ->orWhere(function ($q) use ($from, $to) {
                        $q->where('from', '<=', $from)
                            ->where('to', '>=', $to);
                    });
            })
            ->get();

        if ($records->isEmpty()) {
            return $this->getEmptyData();
        }

        $totalSessions = $records->sum('sessions');
        $totalPageViews = $records->sum('page_views');
        $totalDuration = $records->sum(function ($record) {
            return $record->avg_session_duration * $record->sessions;
        });

        $avgSessionDuration = $totalSessions > 0 ? round($totalDuration / $totalSessions) : 0;

        $totalBounces = $records->sum(function ($record) {
            return ($record->bounce_rate / 100) * $record->sessions;
        });
        $bounceRate = $totalSessions > 0 ? round(($totalBounces / $totalSessions) * 100, 2) : 0;
        $pagesPerSession = $totalSessions > 0 ? round($totalPageViews / $totalSessions, 2) : 0;

        return [
            'visitors' => $records->sum('visitors'),
            'sessions' => $totalSessions,
            'page_views' => $totalPageViews,
            'avg_session_duration' => $avgSessionDuration,
            'bounce_rate' => $bounceRate,
            'pages_per_session' => $pagesPerSession,
            'new_visitors' => $records->sum('new_visitors'),
            'returning_visitors' => $records->sum('returning_visitors'),
            'visitors_desktop' => $records->sum('visitors_desktop'),
            'visitors_mobile' => $records->sum('visitors_mobile'),
            'visitors_tablet' => $records->sum('visitors_tablet'),
        ];
    }

    protected function getLowerFrequency(TimeSeriesFrequencyEnum $frequency): TimeSeriesFrequencyEnum
    {
        return match ($frequency) {
            TimeSeriesFrequencyEnum::WEEKLY => TimeSeriesFrequencyEnum::DAILY,
            TimeSeriesFrequencyEnum::MONTHLY => TimeSeriesFrequencyEnum::DAILY,
            TimeSeriesFrequencyEnum::QUARTERLY => TimeSeriesFrequencyEnum::MONTHLY,
            TimeSeriesFrequencyEnum::YEARLY => TimeSeriesFrequencyEnum::QUARTERLY,
            default => TimeSeriesFrequencyEnum::DAILY,
        };
    }

    protected function getEmptyData(): array
    {
        return [
            'visitors' => 0,
            'sessions' => 0,
            'page_views' => 0,
            'avg_session_duration' => 0,
            'bounce_rate' => 0,
            'pages_per_session' => 0,
            'new_visitors' => 0,
            'returning_visitors' => 0,
            'visitors_desktop' => 0,
            'visitors_mobile' => 0,
            'visitors_tablet' => 0,
        ];
    }
}
