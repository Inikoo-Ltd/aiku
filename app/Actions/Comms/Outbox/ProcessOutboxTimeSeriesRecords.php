<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Outbox;

use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateTimeSeriesNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Comms\Outbox;
use App\Models\Comms\OutboxTimeSeries;
use App\Traits\BuildsAggregatedTimeSeriesQuery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessOutboxTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsAggregatedTimeSeriesQuery;

    public string $jobQueue = 'sales_slave';

    public function getJobUniqueId(int $outboxId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$outboxId:$frequency->value:$from:$to";
    }

    public function handle(int $outboxId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $outbox = Outbox::on('aiku_no_sticky')->find($outboxId);

        if (!$outbox) {
            return;
        }

        $timeSeries = OutboxTimeSeries::on('aiku_no_sticky')
            ->where('outbox_id', $outbox->id)
            ->where('frequency', $frequency->value)
            ->first();

        if (!$timeSeries) {
            $timeSeries = $outbox->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($outbox, $timeSeries, $from, $to);

        OutboxHydrateTimeSeriesNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(Outbox $outbox, OutboxTimeSeries $timeSeries, string $from, string $to): void
    {
        $results = $timeSeries->frequency === TimeSeriesFrequencyEnum::DAILY
            ? $this->fetchDailyResults($outbox, $timeSeries, $from, $to)
            : $this->fetchAggregatedResults($timeSeries, $from, $to);

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $timeSeries->records()->updateOrCreate(
                [
                    'outbox_time_series_id' => $timeSeries->id,
                    'period'                => $period,
                    'frequency'             => $timeSeries->frequency->singleLetter(),
                ],
                [
                    'from'              => $periodFrom,
                    'to'                => $periodTo,
                    'runs'              => $result->runs,
                    'dispatched_emails' => $result->dispatched_emails,
                    'opened_emails'     => $result->opened_emails,
                    'clicked_emails'    => $result->clicked_emails,
                    'bounced_emails'    => $result->bounced_emails,
                    'unsubscribed'      => $result->unsubscribed,
                ]
            );
        }
    }

    protected function fetchDailyResults(Outbox $outbox, OutboxTimeSeries $timeSeries, string $from, string $to): Collection
    {
        $emails = DB::connection('aiku_no_sticky')->table('dispatched_emails')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->where('outbox_id', $timeSeries->outbox_id)
            ->select(
                DB::raw('CAST(created_at AS DATE) as date'),
                DB::raw('COUNT(*) as dispatched_emails'),
                DB::raw("SUM(CASE WHEN state = 'opened' THEN 1 ELSE 0 END) as opened_emails"),
                DB::raw("SUM(CASE WHEN state = 'clicked' THEN 1 ELSE 0 END) as clicked_emails"),
                DB::raw("SUM(CASE WHEN state IN ('soft_bounce', 'hard_bounce') THEN 1 ELSE 0 END) as bounced_emails"),
                DB::raw("SUM(CASE WHEN state = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed")
            )
            ->groupBy(DB::raw('CAST(created_at AS DATE)'))
            ->get()
            ->keyBy('date');

        $runs = $this->fetchDailyRuns($outbox, $from, $to);

        return $emails->keys()->merge($runs->keys())->unique()->values()->map(fn ($date) => (object) [
            'date'              => $date,
            'runs'              => $runs->get($date)->runs ?? 0,
            'dispatched_emails' => $emails->get($date)->dispatched_emails ?? 0,
            'opened_emails'     => $emails->get($date)->opened_emails ?? 0,
            'clicked_emails'    => $emails->get($date)->clicked_emails ?? 0,
            'bounced_emails'    => $emails->get($date)->bounced_emails ?? 0,
            'unsubscribed'      => $emails->get($date)->unsubscribed ?? 0,
        ]);
    }

    protected function fetchDailyRuns(Outbox $outbox, string $from, string $to): Collection
    {
        $table = match ($outbox->model_type) {
            'Mailshot'        => 'mailshots',
            'EmailOngoingRun' => 'email_bulk_runs',
            default           => null,
        };

        if (!$table) {
            return collect();
        }

        return DB::connection('aiku_no_sticky')->table($table)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->where('outbox_id', $outbox->id)
            ->select(
                DB::raw('CAST(created_at AS DATE) as date'),
                DB::raw('COUNT(*) as runs')
            )
            ->groupBy(DB::raw('CAST(created_at AS DATE)'))
            ->get()
            ->keyBy('date');
    }

    protected function fetchAggregatedResults(OutboxTimeSeries $timeSeries, string $from, string $to): Collection
    {
        $dailyTimeSeries = OutboxTimeSeries::on('aiku_no_sticky')
            ->where('outbox_id', $timeSeries->outbox_id)
            ->where('frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->first();

        if (!$dailyTimeSeries) {
            return collect();
        }

        $selects = [
            DB::raw('SUM(runs) as runs'),
            DB::raw('SUM(dispatched_emails) as dispatched_emails'),
            DB::raw('SUM(opened_emails) as opened_emails'),
            DB::raw('SUM(clicked_emails) as clicked_emails'),
            DB::raw('SUM(bounced_emails) as bounced_emails'),
            DB::raw('SUM(unsubscribed) as unsubscribed'),
        ];

        $query = DB::connection('aiku_no_sticky')->table('outbox_time_series_records')
            ->where('outbox_time_series_id', $dailyTimeSeries->id)
            ->where('from', '>=', $from)
            ->where('to', '<=', $to);

        return $this->applyAggregatedFrequencyGrouping($query, $timeSeries->frequency, $selects)->get();
    }
}
