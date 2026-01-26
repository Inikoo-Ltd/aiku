<?php

namespace App\Actions\Web\Webpage;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessWebpageTimeSeries
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'process:webpage-time-series';
    public string $commandDescription = 'Process time series data for all webpages for a given frequency.';

    public function asCommand(Command $command): void
    {
        $frequencyInput = $command->option('frequency');
        $frequency = TimeSeriesFrequencyEnum::tryFrom($frequencyInput);

        if (!$frequency) {
            $command->error("Invalid frequency provided: $frequencyInput");
            return;
        }

        [$dateFrom, $dateTo] = match ($frequency) {
            TimeSeriesFrequencyEnum::DAILY => [Carbon::yesterday()->format('Y-m-d'), Carbon::yesterday()->format('Y-m-d')],
            TimeSeriesFrequencyEnum::WEEKLY => [Carbon::now()->subWeek()->startOfWeek()->format('Y-m-d'), Carbon::now()->subWeek()->endOfWeek()->format('Y-m-d')],
            TimeSeriesFrequencyEnum::MONTHLY => [Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d'), Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d')],
            TimeSeriesFrequencyEnum::QUARTERLY => [Carbon::now()->subQuarter()->startOfQuarter()->format('Y-m-d'), Carbon::now()->subQuarter()->endOfQuarter()->format('Y-m-d')],
            TimeSeriesFrequencyEnum::YEARLY => [Carbon::now()->subYear()->startOfYear()->format('Y-m-d'), Carbon::now()->subYear()->endOfYear()->format('Y-m-d')],
        };

        $totalWebpages = Webpage::where('state', WebpageStateEnum::LIVE)->count();
        $bar = $command->getOutput()->createProgressBar($totalWebpages);
        $bar->setFormat('%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        Webpage::where('state', WebpageStateEnum::LIVE)
            ->chunkById(200, function ($webpages) use ($bar, $frequency, $dateFrom, $dateTo) {
                foreach ($webpages as $webpage) {
                    $hasData = false;
                    if ($frequency == TimeSeriesFrequencyEnum::DAILY) {
                        $hasData = DB::table('website_page_views')->where('webpage_id', $webpage->id)->exists() ||
                                   DB::table('website_conversion_events')->where('webpage_id', $webpage->id)->exists();
                    } else {
                        $hasData = DB::table('webpage_time_series')
                            ->join('webpage_time_series_records', 'webpage_time_series.id', '=', 'webpage_time_series_records.webpage_time_series_id')
                            ->where('webpage_time_series.webpage_id', $webpage->id)
                            ->where('webpage_time_series_records.frequency', 'D')
                            ->whereBetween('webpage_time_series_records.from', [$dateFrom, $dateTo])
                            ->exists();
                    }

                    if ($hasData) {
                        ProcessWebpageTimeSeriesRecords::run(
                            $webpage->id,
                            $frequency,
                            $dateFrom,
                            $dateTo
                        );
                    }
                    $bar->advance();
                }
            });

        $bar->finish();
        $command->newLine();
    }

    public function handle(TimeSeriesFrequencyEnum $frequency, string $dateFrom, string $dateTo): void
    {
        Webpage::where('state', WebpageStateEnum::LIVE)
            ->chunkById(200, function ($webpages) use ($frequency, $dateFrom, $dateTo) {
                foreach ($webpages as $webpage) {
                    $hasData = false;
                    if ($frequency == TimeSeriesFrequencyEnum::DAILY) {
                        $hasData = DB::table('website_page_views')->where('webpage_id', $webpage->id)->exists() ||
                                   DB::table('website_conversion_events')->where('webpage_id', $webpage->id)->exists();
                    } else {
                        $hasData = DB::table('webpage_time_series')
                            ->join('webpage_time_series_records', 'webpage_time_series.id', '=', 'webpage_time_series_records.webpage_time_series_id')
                            ->where('webpage_time_series.webpage_id', $webpage->id)
                            ->where('webpage_time_series_records.frequency', 'D')
                            ->whereBetween('webpage_time_series_records.from', [$dateFrom, $dateTo])
                            ->exists();
                    }

                    if ($hasData) {
                        ProcessWebpageTimeSeriesRecords::run(
                            $webpage->id,
                            $frequency,
                            $dateFrom,
                            $dateTo
                        );
                    }
                }
            });
    }

    public function getCommandSignature(): string
    {
        $frequencies = implode(',', array_column(TimeSeriesFrequencyEnum::cases(), 'value'));
        return "{$this->commandSignature} {--frequency=daily : The frequency to process ($frequencies)}";
    }
}
