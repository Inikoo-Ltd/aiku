<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 12 May 2026 11:31:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\MailshotTimeSeries;

use App\Actions\Comms\MailshotTimeSeries\Hydrators\MailshotTimeSeriesHydrateNumberRecords;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Comms\Mailshot;
use App\Models\Comms\MailshotTimeSeries;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessMailshotTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'comms_slave';

    public function getJobUniqueId(int $mailshotId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$mailshotId:$frequency->value:$from:$to";
    }

    public function handle(int $mailshotId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $mailshot = Mailshot::find($mailshotId);

        if (!$mailshot) {
            return;
        }

        $timeSeries = MailshotTimeSeries::where('mailshot_id', $mailshot->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $mailshot->timeSeries()->create([
                'frequency' => $frequency,
                'from'      => $from,
                'to'        => $to
            ]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        MailshotTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(MailshotTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];

        $query = DB::connection('aiku_no_sticky')->table('dispatched_emails')
            ->where('mailshot_id', $timeSeries->mailshot_id)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->whereNull('deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $metrics = $this->getEmailTrackingStats($timeSeries->mailshot_id, $periodFrom, $periodTo);

            $timeSeries->records()->updateOrCreate(
                [
                    'mailshot_time_series_id' => $timeSeries->id,
                    'period'                   => $period,
                    'frequency'                => $timeSeries->frequency->singleLetter()
                ],
                [
                    'from'                                                    => $periodFrom,
                    'to'                                                      => $periodTo,
                    'number_dispatched_emails'                                 => $result->total_dispatched ?? 0,
                    'number_dispatched_emails_state_ready'                      => $result->state_ready ?? 0,
                    'number_dispatched_emails_state_sent_to_provider'           => $result->state_sent_to_provider ?? 0,
                    'number_dispatched_emails_state_error'                      => $result->state_error ?? 0,
                    'number_dispatched_emails_state_rejected_by_provider'      => $result->state_rejected_by_provider ?? 0,
                    'number_dispatched_emails_state_sent'                      => $result->state_sent ?? 0,
                    'number_dispatched_emails_state_delivered'                 => $result->state_delivered ?? 0,
                    'number_dispatched_emails_state_hard_bounce'               => $result->state_hard_bounce ?? 0,
                    'number_dispatched_emails_state_soft_bounce'               => $result->state_soft_bounce ?? 0,
                    'number_dispatched_emails_state_opened'                    => $result->state_opened ?? 0,
                    'number_dispatched_emails_state_clicked'                   => $result->state_clicked ?? 0,
                    'number_dispatched_emails_state_spam'                      => $result->state_spam ?? 0,
                    'number_dispatched_emails_state_unsubscribed'               => $result->state_unsubscribed ?? 0,
                    'number_provoked_unsubscribe'                               => $metrics['provoked_unsubscribes'] ?? 0,
                    ...$metrics,
                ]
            );

            $processedPeriods[] = $period;
        }

        $this->processPeriodsWithoutActivity($timeSeries, $from, $to, $processedPeriods);
    }

    protected function processPeriodsWithoutActivity(MailshotTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): void
    {
        $nonActivityPeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($nonActivityPeriods as $periodData) {
            $metrics = $this->getEmailTrackingStats($timeSeries->mailshot_id, $periodData['from'], $periodData['to']);

            $hasActivity = collect($metrics)->some(fn ($value) => $value != 0 && $value !== null);

            if (!$hasActivity) {
                continue;
            }

            $timeSeries->records()->updateOrCreate(
                [
                    'mailshot_time_series_id' => $timeSeries->id,
                    'period'                   => $periodData['period'],
                    'frequency'                => $timeSeries->frequency->singleLetter()
                ],
                [
                    'from'                                                    => $periodData['from'],
                    'to'                                                      => $periodData['to'],
                    'number_dispatched_emails'                                 => 0,
                    'number_dispatched_emails_state_ready'                      => 0,
                    'number_dispatched_emails_state_sent_to_provider'           => 0,
                    'number_dispatched_emails_state_error'                      => 0,
                    'number_dispatched_emails_state_rejected_by_provider'      => 0,
                    'number_dispatched_emails_state_sent'                      => 0,
                    'number_dispatched_emails_state_delivered'                 => 0,
                    'number_dispatched_emails_state_hard_bounce'               => 0,
                    'number_dispatched_emails_state_soft_bounce'               => 0,
                    'number_dispatched_emails_state_opened'                    => 0,
                    'number_dispatched_emails_state_clicked'                   => 0,
                    'number_dispatched_emails_state_spam'                      => 0,
                    'number_dispatched_emails_state_unsubscribed'               => 0,
                    'number_provoked_unsubscribe'                               => $metrics['provoked_unsubscribes'] ?? 0,
                    ...$metrics,
                ]
            );
        }
    }

    protected function getEmailTrackingStats(int $mailshotId, Carbon $periodFrom, Carbon $periodTo): array
    {
        $trackingStats = [
            'provoked_unsubscribes' => 0,
        ];

        // Get provoked unsubscribes from dispatched emails
        $unsubscribesResult = DB::connection('aiku_no_sticky')->table('dispatched_emails')
            ->where('mailshot_id', $mailshotId)
            ->where('created_at', '>=', $periodFrom)
            ->where('created_at', '<=', $periodTo)
            ->where('provoked_unsubscribe', true)
            ->count();

        $trackingStats['provoked_unsubscribes'] = $unsubscribesResult;

        return $trackingStats;
    }

    protected function applyFrequencyGrouping($query, TimeSeriesFrequencyEnum $frequency)
    {
        return $query->selectRaw('
                COUNT(*) as total_dispatched,
                SUM(CASE WHEN state = ? THEN 1 ELSE 0 END) as state_ready,
                                SUM(CASE WHEN state = ? THEN 1 ELSE 0 END) as state_error,
                SUM(CASE WHEN state = ? THEN 1 ELSE 0 END) as state_rejected_by_provider,
                SUM(CASE WHEN state = ? THEN 1 ELSE 0 END) as state_sent,
                SUM(CASE WHEN state = ? THEN 1 ELSE 0 END) as state_delivered,
                SUM(CASE WHEN state = ? THEN 1 ELSE 0 END) as state_hard_bounce,
                SUM(CASE WHEN state = ? THEN 1 ELSE 0 END) as state_soft_bounce,
                SUM(CASE WHEN state = ? THEN 1 ELSE 0 END) as state_opened,
                SUM(CASE WHEN state = ? THEN 1 ELSE 0 END) as state_clicked,
                SUM(CASE WHEN state = ? THEN 1 ELSE 0 END) as state_spam,
                SUM(CASE WHEN state = ? THEN 1 ELSE 0 END) as state_unsubscribed,
                ' . $this->getFrequencyDateSelect($frequency) . '
            ', [
            DispatchedEmailStateEnum::READY->value,
            DispatchedEmailStateEnum::ERROR->value,
            DispatchedEmailStateEnum::REJECTED_BY_PROVIDER->value,
            DispatchedEmailStateEnum::SENT->value,
            DispatchedEmailStateEnum::DELIVERED->value,
            DispatchedEmailStateEnum::HARD_BOUNCE->value,
            DispatchedEmailStateEnum::SOFT_BOUNCE->value,
            DispatchedEmailStateEnum::OPENED->value,
            DispatchedEmailStateEnum::CLICKED->value,
            DispatchedEmailStateEnum::SPAM->value,
            DispatchedEmailStateEnum::UNSUBSCRIBED->value,
        ])
            ->groupBy($this->getFrequencyGroupBy($frequency));
    }

    protected function getFrequencyDateSelect(TimeSeriesFrequencyEnum $frequency): string
    {
        return match ($frequency) {
            TimeSeriesFrequencyEnum::YEARLY    => "DATE_TRUNC('year', created_at) as period_date",
            TimeSeriesFrequencyEnum::QUARTERLY => "DATE_TRUNC('quarter', created_at) as period_date",
            TimeSeriesFrequencyEnum::MONTHLY   => "DATE_TRUNC('month', created_at) as period_date",
            TimeSeriesFrequencyEnum::WEEKLY    => "DATE_TRUNC('week', created_at) as period_date",
            TimeSeriesFrequencyEnum::DAILY     => "DATE(created_at) as period_date",
        };
    }

    protected function getFrequencyGroupBy(TimeSeriesFrequencyEnum $frequency): string
    {
        return match ($frequency) {
            TimeSeriesFrequencyEnum::YEARLY    => "DATE_TRUNC('year', created_at)",
            TimeSeriesFrequencyEnum::QUARTERLY => "DATE_TRUNC('quarter', created_at)",
            TimeSeriesFrequencyEnum::MONTHLY   => "DATE_TRUNC('month', created_at)",
            TimeSeriesFrequencyEnum::WEEKLY    => "DATE_TRUNC('week', created_at)",
            TimeSeriesFrequencyEnum::DAILY     => "DATE(created_at)",
        };
    }
}
