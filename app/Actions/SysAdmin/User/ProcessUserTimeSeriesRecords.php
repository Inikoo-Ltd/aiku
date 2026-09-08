<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 10:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\SysAdmin\UserTimeSeries;
use App\Models\SysAdmin\UserTimeSeriesRecord;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessUserTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'long-low-priority';

    public string $commandSignature = 'users:process_time_series {--from= : Start date (Y-m-d), defaults to today} {--to= : End date (Y-m-d), defaults to today} {--F|frequency= : Single frequency (daily|weekly|monthly|quarterly|yearly)}';

    public function getJobUniqueId(TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$frequency->value:$from:$to";
    }

    public function handle(TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        [$from, $to] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

        $truncUnit = match ($frequency) {
            TimeSeriesFrequencyEnum::DAILY     => 'day',
            TimeSeriesFrequencyEnum::WEEKLY    => 'week',
            TimeSeriesFrequencyEnum::MONTHLY   => 'month',
            TimeSeriesFrequencyEnum::QUARTERLY => 'quarter',
            TimeSeriesFrequencyEnum::YEARLY    => 'year',
        };

        $requests = DB::connection('aiku_no_sticky')->table('user_requests')
            ->selectRaw("user_id, date_trunc('$truncUnit', date) as bucket, count(*) as number_requests, count(distinct date::date) as number_active_days")
            ->whereBetween('date', [$from, $to])
            ->groupBy('user_id', 'bucket')
            ->get();

        $logins = DB::connection('aiku_no_sticky')->table('user_logins')
            ->selectRaw("user_id, date_trunc('$truncUnit', date) as bucket, count(*) as number_logins")
            ->whereNotNull('user_id')
            ->whereBetween('date', [$from, $to])
            ->groupBy('user_id', 'bucket')
            ->get();

        $metrics = [];
        foreach ($requests as $row) {
            $metrics[$row->user_id.'|'.$row->bucket] = [
                'user_id'            => $row->user_id,
                'bucket'             => $row->bucket,
                'number_requests'    => (int) $row->number_requests,
                'number_active_days' => (int) $row->number_active_days,
                'number_logins'      => 0,
            ];
        }
        foreach ($logins as $row) {
            $key = $row->user_id.'|'.$row->bucket;
            $metrics[$key] ??= [
                'user_id'            => $row->user_id,
                'bucket'             => $row->bucket,
                'number_requests'    => 0,
                'number_active_days' => 0,
                'number_logins'      => 0,
            ];
            $metrics[$key]['number_logins'] = (int) $row->number_logins;
        }

        if ($metrics === []) {
            return;
        }

        $timeSeriesIds = UserTimeSeries::where('frequency', $frequency->value)->pluck('id', 'user_id');

        $touchedTimeSeriesIds = [];
        $now                  = now();
        $rows                 = [];

        foreach ($metrics as $metric) {
            $timeSeriesId = $timeSeriesIds[$metric['user_id']]
                ?? UserTimeSeries::create(['user_id' => $metric['user_id'], 'frequency' => $frequency])->id;
            $timeSeriesIds[$metric['user_id']] = $timeSeriesId;

            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] =
                TimeSeriesPeriodCalculator::resolvePeriodFromDate(Carbon::parse($metric['bucket']), $frequency);

            $rows[] = [
                'user_time_series_id' => $timeSeriesId,
                'frequency'           => $frequency->singleLetter(),
                'period'              => $period,
                'from'                => $periodFrom,
                'to'                  => $periodTo,
                'number_requests'     => $metric['number_requests'],
                'number_active_days'  => $metric['number_active_days'],
                'number_logins'       => $metric['number_logins'],
                'created_at'          => $now,
                'updated_at'          => $now,
            ];

            $touchedTimeSeriesIds[$timeSeriesId] = true;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            UserTimeSeriesRecord::upsert(
                $chunk,
                ['user_time_series_id', 'period'],
                ['from', 'to', 'number_requests', 'number_active_days', 'number_logins', 'updated_at']
            );
        }

        UserTimeSeries::whereIn('id', array_keys($touchedTimeSeriesIds))->update([
            'from'           => DB::raw('(select min("from") from user_time_series_records where user_time_series_id = user_time_series.id)'),
            'to'             => DB::raw('(select max("to") from user_time_series_records where user_time_series_id = user_time_series.id)'),
            'number_records' => DB::raw('(select count(*) from user_time_series_records where user_time_series_id = user_time_series.id)'),
        ]);
    }

    public function asCommand($command): int
    {
        $from = $command->option('from') ?? now()->toDateString();
        $to   = $command->option('to') ?? now()->toDateString();

        $frequencies = $command->option('frequency')
            ? [TimeSeriesFrequencyEnum::from($command->option('frequency'))]
            : TimeSeriesFrequencyEnum::cases();

        foreach ($frequencies as $frequency) {
            $command->info("Processing $frequency->value user time series records $from → $to");
            $this->handle($frequency, $from, $to);
        }

        return 0;
    }
}
