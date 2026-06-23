<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 23 Jun 2026 00:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Enums\Comms\Mailshot\MailshotPerformanceInsightMetricEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Comms\Mailshot;
use App\Models\Comms\MailshotStats;
use App\Models\Comms\MailshotTimeSeries;
use App\Models\Comms\MailshotTimeSeriesRecord;
use Carbon\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMailshotPerformanceInsights
{
    use AsAction;

    public function handle(Mailshot $mailshot, ?string $frequency = null, ?string $metric = null): array
    {
        $mailshotStat = $mailshot->stats;

        $frequencyEnum = match ($frequency) {
            'week'  => TimeSeriesFrequencyEnum::WEEKLY,
            'month' => TimeSeriesFrequencyEnum::MONTHLY,
            default => TimeSeriesFrequencyEnum::DAILY,
        };
        $metricEnum = MailshotPerformanceInsightMetricEnum::tryFrom($metric) ?? MailshotPerformanceInsightMetricEnum::OPEN_RATE;

        $timeSeries = MailshotTimeSeries::where('mailshot_id', $mailshot->id)
            ->where('frequency', $frequencyEnum)
            ->first();

        if (!$timeSeries) {
            return [];
        }

        $metric = $timeSeries->records->map(function (MailshotTimeSeriesRecord $record) use ($frequencyEnum, $metricEnum) {
            return [
                'period' => $this->formatPeriod($record->from, $record->to, $frequencyEnum),
                'value'  => $this->resolveMetricValue($record, $metricEnum),
            ];
        })->values()->all();

        return [
            'metric' => $metric,
            'total'   => $this->resolveMetricTotal($mailshotStat, $metricEnum)
        ];

    }

    protected function resolveMetricValue(MailshotTimeSeriesRecord $record, MailshotPerformanceInsightMetricEnum $metric): float
    {
        //  TODO: FIX calculation
        return match ($metric) {
            MailshotPerformanceInsightMetricEnum::TOTAL_EMAIL_OPENED => (float) $record->number_dispatched_emails_state_opened,
            MailshotPerformanceInsightMetricEnum::TOTAL_CLICK        => (float) $record->number_dispatched_emails_state_clicked,
            MailshotPerformanceInsightMetricEnum::OPEN_RATE          => $record->openRate(),
            MailshotPerformanceInsightMetricEnum::CLICK_RATE         => $record->clickedRate(),
            MailshotPerformanceInsightMetricEnum::SPAM_RATE          => $record->spamRate(),
            MailshotPerformanceInsightMetricEnum::UNSUBSCRIBE_RATE   => $record->unsubscribeRate(),
            MailshotPerformanceInsightMetricEnum::BOUNCE_RATE        => $record->bounceRate(),
        };
    }

    protected function resolveMetricTotal(MailshotStats $stats, MailshotPerformanceInsightMetricEnum $metric): float|int
    {
        //  TODO: FIX calculation
        return match ($metric) {
            MailshotPerformanceInsightMetricEnum::TOTAL_EMAIL_OPENED => $stats->number_dispatched_emails_state_opened,
            MailshotPerformanceInsightMetricEnum::TOTAL_CLICK        => $stats->number_dispatched_emails_state_clicked,
            MailshotPerformanceInsightMetricEnum::OPEN_RATE          => $stats->number_dispatched_emails_state_opened,
            MailshotPerformanceInsightMetricEnum::CLICK_RATE         => $stats->number_dispatched_emails_state_opened,
            MailshotPerformanceInsightMetricEnum::SPAM_RATE          => $stats->number_dispatched_emails_state_opened,
            MailshotPerformanceInsightMetricEnum::UNSUBSCRIBE_RATE   => $stats->number_dispatched_emails_state_opened,
            MailshotPerformanceInsightMetricEnum::BOUNCE_RATE        => $stats->number_dispatched_emails_state_opened,
        };
    }

    protected function formatPeriod(?Carbon $from, ?Carbon $to, TimeSeriesFrequencyEnum $frequency): string
    {
        if (!$from) {
            return '-';
        }

        return match ($frequency) {
            TimeSeriesFrequencyEnum::DAILY => $from->format('d M Y'),
            TimeSeriesFrequencyEnum::WEEKLY => $from->format('d M').' - '.($to ? $to->format('d M Y') : ''),
            TimeSeriesFrequencyEnum::MONTHLY => $from->format('M Y'),
            TimeSeriesFrequencyEnum::QUARTERLY => 'Q'.$from->quarter.' '.$from->format('Y'),
            TimeSeriesFrequencyEnum::YEARLY => $from->format('Y'),
        };
    }

    public function asController(Mailshot $mailshot, ActionRequest $request): array
    {
        return $this->handle($mailshot, $request->input('frequency'), $request->input('metric'));
    }
}
