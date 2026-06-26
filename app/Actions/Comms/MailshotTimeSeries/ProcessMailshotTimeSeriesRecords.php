<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 12 May 2026 11:31:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\MailshotTimeSeries;

use App\Actions\Comms\MailshotTimeSeries\Hydrators\MailshotTimeSeriesHydrateNumberRecords;
use App\Enums\Comms\EmailTrackingEvent\EmailTrackingEventTypeEnum;
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

    public string $jobQueue = 'ses-analytics';

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
            ]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        MailshotTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(MailshotTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];

        $query = DB::connection()->table('email_tracking_events')
            ->rightJoin('dispatched_emails', 'email_tracking_events.dispatched_email_id', '=', 'dispatched_emails.id')
            ->rightJoin('mailshot_has_dispatched_emails', 'dispatched_emails.id', '=', 'mailshot_has_dispatched_emails.dispatched_email_id')
            ->where('mailshot_has_dispatched_emails.mailshot_id', $timeSeries->mailshot_id)
            ->where('email_tracking_events.created_at', '>=', $from)
            ->where('email_tracking_events.created_at', '<=', $to);

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
                ]
            );
        }
    }

    protected function getEmailTrackingStats(int $mailshotId, Carbon $periodFrom, Carbon $periodTo): array
    {
        $trackingStats = [
            'provoked_unsubscribes' => 0,
        ];

        $unsubscribesResult = DB::connection()->table('email_tracking_events')
            ->join('dispatched_emails', 'email_tracking_events.dispatched_email_id', '=', 'dispatched_emails.id')
            ->join('mailshot_has_dispatched_emails', 'dispatched_emails.id', '=', 'mailshot_has_dispatched_emails.dispatched_email_id')
            ->where('mailshot_has_dispatched_emails.mailshot_id', $mailshotId)
            ->where('email_tracking_events.created_at', '>=', $periodFrom)
            ->where('email_tracking_events.created_at', '<=', $periodTo)
            ->where('dispatched_emails.provoked_unsubscribe', true)
            ->count();

        $trackingStats['provoked_unsubscribes'] = $unsubscribesResult;

        return $trackingStats;
    }


    protected function applyFrequencyGrouping($query, TimeSeriesFrequencyEnum $frequency)
    {
        $inner = $query->selectRaw('
            email_tracking_events.type,
            email_tracking_events.dispatched_email_id,
            email_tracking_events.created_at,
            CAST(email_tracking_events.created_at AS DATE) as event_date
        ');

        $states = [
            'state_error'                 => EmailTrackingEventTypeEnum::ERROR->value,
            'state_rejected_by_provider'   => EmailTrackingEventTypeEnum::DECLINED_BY_PROVIDER->value,
            'state_sent'                   => EmailTrackingEventTypeEnum::SENT->value,
            'state_delivered'              => EmailTrackingEventTypeEnum::DELIVERED->value,
            'state_hard_bounce'            => EmailTrackingEventTypeEnum::HARD_BOUNCE->value,
            'state_soft_bounce'            => EmailTrackingEventTypeEnum::SOFT_BOUNCE->value,
            'state_opened'                 => EmailTrackingEventTypeEnum::OPENED->value,
            'state_clicked'                => EmailTrackingEventTypeEnum::CLICKED->value,
            'state_spam'                   => EmailTrackingEventTypeEnum::MARKED_AS_SPAM->value,
            'state_unsubscribed'           => EmailTrackingEventTypeEnum::UNSUBSCRIBED->value,
        ];

        $bindings = [];
        $selects  = ['COUNT(*) as total_dispatched'];

        foreach ($states as $alias => $type) {
            $selects[]  = "COUNT(DISTINCT CASE WHEN e.type = ? THEN CONCAT(e.dispatched_email_id::text, '_', e.event_date::text) ELSE NULL END) as $alias";
            $bindings[] = $type;
        }

        $selects[] = $this->getFrequencyDateSelect($frequency);

        return DB::connection()->query()
            ->fromSub($inner, 'e')
            ->selectRaw(implode(",\n", $selects), $bindings)
            ->groupByRaw($this->getFrequencyGroupBy($frequency));
    }

    protected function getFrequencyDateSelect(TimeSeriesFrequencyEnum $frequency): string
    {
        return match ($frequency) {
            TimeSeriesFrequencyEnum::YEARLY    => "EXTRACT(YEAR FROM e.created_at) as year",
            TimeSeriesFrequencyEnum::QUARTERLY => "EXTRACT(YEAR FROM e.created_at) as year, EXTRACT(QUARTER FROM e.created_at) as quarter",
            TimeSeriesFrequencyEnum::MONTHLY   => "EXTRACT(YEAR FROM e.created_at) as year, EXTRACT(MONTH FROM e.created_at) as month",
            TimeSeriesFrequencyEnum::WEEKLY    => "EXTRACT(YEAR FROM e.created_at) as year, EXTRACT(WEEK FROM e.created_at) as week",
            TimeSeriesFrequencyEnum::DAILY     => "e.event_date as date",
        };
    }

    protected function getFrequencyGroupBy(TimeSeriesFrequencyEnum $frequency): string
    {
        return match ($frequency) {
            TimeSeriesFrequencyEnum::YEARLY    => "EXTRACT(YEAR FROM e.created_at)",
            TimeSeriesFrequencyEnum::QUARTERLY => "EXTRACT(YEAR FROM e.created_at), EXTRACT(QUARTER FROM e.created_at)",
            TimeSeriesFrequencyEnum::MONTHLY   => "EXTRACT(YEAR FROM e.created_at), EXTRACT(MONTH FROM e.created_at)",
            TimeSeriesFrequencyEnum::WEEKLY    => "EXTRACT(YEAR FROM e.created_at), EXTRACT(WEEK FROM e.created_at)",
            TimeSeriesFrequencyEnum::DAILY     => "e.event_date",
        };
    }
}
