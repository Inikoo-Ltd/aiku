<?php

namespace App\Actions\Web;

use App\Actions\Web\Webpage\ProcessWebpageTimeSeries;
use App\Actions\Web\Website\ProcessWebsiteTimeSeries;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessTimeSeries
{
    use AsAction;

    public string $commandSignature = 'process:time-series';
    public string $commandDescription = 'Dispatch jobs to process time series data for websites and webpages.';

    public function asCommand(Command $command): void
    {
        $frequencyInput = $command->option('frequency');
        $frequency = TimeSeriesFrequencyEnum::tryFrom($frequencyInput);

        if (!$frequency) {
            $command->error("Invalid frequency provided: $frequencyInput");

            return;
        }

        [$dateFrom, $dateTo] = match ($frequency) {
            TimeSeriesFrequencyEnum::DAILY => [
                Carbon::yesterday()->format('Y-m-d'),
                Carbon::yesterday()->format('Y-m-d')
            ],
            TimeSeriesFrequencyEnum::WEEKLY => [
                Carbon::now()->subWeek()->startOfWeek()->format('Y-m-d'),
                Carbon::now()->subWeek()->endOfWeek()->format('Y-m-d')
            ],
            TimeSeriesFrequencyEnum::MONTHLY => [
                Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d'),
                Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d')
            ],
            TimeSeriesFrequencyEnum::QUARTERLY => [
                Carbon::now()->subQuarter()->startOfQuarter()->format('Y-m-d'),
                Carbon::now()->subQuarter()->endOfQuarter()->format('Y-m-d')
            ],
            TimeSeriesFrequencyEnum::YEARLY => [
                Carbon::now()->subYear()->startOfYear()->format('Y-m-d'),
                Carbon::now()->subYear()->endOfYear()->format('Y-m-d')
            ],
        };

        ProcessWebsiteTimeSeries::dispatch($frequency, $dateFrom, $dateTo);
        ProcessWebpageTimeSeries::dispatch($frequency, $dateFrom, $dateTo);
    }

    public function getCommandSignature(): string
    {
        $frequencies = implode(',', TimeSeriesFrequencyEnum::values());
        return "process:time-series {--frequency=daily : The frequency to process ($frequencies)}";
    }
}
