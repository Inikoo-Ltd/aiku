<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 12:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\AI;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Events\BroadcastAiUsageChanged;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Helpers\AiTimeSeries;
use App\Models\Helpers\AiTimeSeriesRecord;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Rolls the calls saved in ai_usages up into one time series per feature. Dispatched for today after
 * every AI call and swept nightly; each run tells open AI dashboards over the websocket to reload.
 */
class ProcessAiTimeSeriesRecords implements ShouldBeUniqueUntilProcessing
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public string $commandSignature = 'ai:process_time_series {--from= : Start date (Y-m-d), defaults to today} {--to= : End date (Y-m-d), defaults to today} {--F|frequency= : Single frequency (daily|weekly|monthly|quarterly|yearly)}';

    public function getJobUniqueId(string $from, string $to, ?TimeSeriesFrequencyEnum $frequency = null): string
    {
        return "$from:$to:".($frequency->value ?? 'all');
    }

    public function handle(string $from, string $to, ?TimeSeriesFrequencyEnum $frequency = null): void
    {
        foreach ($frequency ? [$frequency] : TimeSeriesFrequencyEnum::cases() as $processFrequency) {
            $this->processFrequency($processFrequency, $from, $to);
        }

        BroadcastAiUsageChanged::dispatch();
    }

    protected function processFrequency(TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        [$from, $to] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

        $truncUnit = match ($frequency) {
            TimeSeriesFrequencyEnum::DAILY     => 'day',
            TimeSeriesFrequencyEnum::WEEKLY    => 'week',
            TimeSeriesFrequencyEnum::MONTHLY   => 'month',
            TimeSeriesFrequencyEnum::QUARTERLY => 'quarter',
            TimeSeriesFrequencyEnum::YEARLY    => 'year',
        };

        $metrics = DB::table('ai_usages')
            ->selectRaw("feature, date_trunc('$truncUnit', created_at) as bucket, count(*) as number_calls, sum(prompt_tokens) as prompt_tokens, sum(completion_tokens) as completion_tokens, coalesce(sum(cost), 0) as cost")
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('feature', 'bucket')
            ->get();

        if ($metrics->isEmpty()) {
            return;
        }

        $timeSeriesIds = AiTimeSeries::where('frequency', $frequency->value)->pluck('id', 'feature');
        $now           = now();
        $rows          = [];

        foreach ($metrics as $metric) {
            $timeSeriesId = $timeSeriesIds[$metric->feature]
                ?? AiTimeSeries::firstOrCreate(['feature' => $metric->feature, 'frequency' => $frequency])->id;
            $timeSeriesIds[$metric->feature] = $timeSeriesId;

            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] =
                TimeSeriesPeriodCalculator::resolvePeriodFromDate(Carbon::parse($metric->bucket), $frequency);

            $rows[] = [
                'ai_time_series_id' => $timeSeriesId,
                'frequency'         => $frequency->singleLetter(),
                'period'            => $period,
                'from'              => $periodFrom,
                'to'                => $periodTo,
                'number_calls'      => (int) $metric->number_calls,
                'prompt_tokens'     => (int) $metric->prompt_tokens,
                'completion_tokens' => (int) $metric->completion_tokens,
                'cost'              => $metric->cost,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            AiTimeSeriesRecord::upsert(
                $chunk,
                ['ai_time_series_id', 'period'],
                ['from', 'to', 'number_calls', 'prompt_tokens', 'completion_tokens', 'cost', 'updated_at']
            );
        }

        AiTimeSeries::whereIn('id', array_unique(array_column($rows, 'ai_time_series_id')))->update([
            'from'           => DB::raw('(select min("from") from ai_time_series_records where ai_time_series_id = ai_time_series.id)'),
            'to'             => DB::raw('(select max("to") from ai_time_series_records where ai_time_series_id = ai_time_series.id)'),
            'number_records' => DB::raw('(select count(*) from ai_time_series_records where ai_time_series_id = ai_time_series.id)'),
        ]);
    }

    public function asCommand(Command $command): int
    {
        $from = $command->option('from') ?? now()->toDateString();
        $to   = $command->option('to') ?? now()->toDateString();

        $this->handle($from, $to, $command->option('frequency') ? TimeSeriesFrequencyEnum::from($command->option('frequency')) : null);

        $command->info("AI time series processed $from → $to");

        return 0;
    }
}
