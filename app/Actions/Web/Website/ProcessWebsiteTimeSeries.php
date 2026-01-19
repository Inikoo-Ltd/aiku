<?php

namespace App\Actions\Web\Website;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessWebsiteTimeSeries
{
    use AsAction;

    public string $commandSignature = 'process:website-time-series';
    public string $commandDescription = 'Process time series data for all websites for a given frequency.';

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

        $totalWebsites = Website::where('status', true)->count();
        $bar = $command->getOutput()->createProgressBar($totalWebsites);
        $bar->setFormat('%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        Website::where('status', true)
            ->chunkById(200, function ($websites) use ($bar, $frequency, $dateFrom, $dateTo) {
                foreach ($websites as $website) {
                    $hasData = false;
                    if ($frequency == TimeSeriesFrequencyEnum::DAILY) {
                        $hasData = DB::table('website_visitors')->where('website_id', $website->id)->exists() ||
                                   DB::table('website_page_views')->where('website_id', $website->id)->exists() ||
                                   DB::table('website_conversion_events')->where('website_id', $website->id)->exists();
                    } else {
                        $hasData = DB::table('website_time_series')
                            ->join('website_time_series_records', 'website_time_series.id', '=', 'website_time_series_records.website_time_series_id')
                            ->where('website_time_series.website_id', $website->id)
                            ->where('website_time_series_records.frequency', 'D')
                            ->whereBetween('website_time_series_records.from', [$dateFrom, $dateTo])
                            ->exists();
                    }

                    if ($hasData) {
                        ProcessWebsiteTimeSeriesRecords::run(
                            $website->id,
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
        Website::where('status', true)
            ->chunkById(200, function ($websites) use ($frequency, $dateFrom, $dateTo) {
                foreach ($websites as $website) {
                    $hasData = false;
                    if ($frequency == TimeSeriesFrequencyEnum::DAILY) {
                        $hasData = DB::table('website_visitors')->where('website_id', $website->id)->exists() ||
                                   DB::table('website_page_views')->where('website_id', $website->id)->exists() ||
                                   DB::table('website_conversion_events')->where('website_id', $website->id)->exists();
                    } else {
                        $hasData = DB::table('website_time_series')
                            ->join('website_time_series_records', 'website_time_series.id', '=', 'website_time_series_records.website_time_series_id')
                            ->where('website_time_series.website_id', $website->id)
                            ->where('website_time_series_records.frequency', 'D')
                            ->whereBetween('website_time_series_records.from', [$dateFrom, $dateTo])
                            ->exists();
                    }

                    if ($hasData) {
                        ProcessWebsiteTimeSeriesRecords::run(
                            $website->id,
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
