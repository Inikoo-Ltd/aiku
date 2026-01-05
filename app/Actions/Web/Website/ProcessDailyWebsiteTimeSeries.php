<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Carbon;

class ProcessDailyWebsiteTimeSeries
{
    use AsAction;

    public string $commandSignature = 'process-websites-daily-time-series';

    public function asCommand(Command $command): void
    {
        $websites = Website::where('status', true)->get();
        $date = Carbon::yesterday()->format('Y-m-d');

        $command->info("Processing daily time series for {$websites->count()} websites for date: {$date}");

        foreach ($websites as $website) {
            $command->info("Processing website: {$website->domain} ({$website->id})");

            // If the raw visitor data is truncated below, switch to ::run() for synchronous execution
            // to ensure data is aggregated before deletion.
            ProcessWebsiteTimeSeriesRecords::dispatch(
                $website->id,
                TimeSeriesFrequencyEnum::DAILY,
                $date,
                $date
            );
        }

        // Optional: Purge raw visitor logs to optimize storage.
        // WARNING: Ensure processing is synchronous (::run) before enabling to prevent data loss.
        // $command->info("Truncating website_visitors table...");
        // DB::table('website_visitors')->truncate();

        $command->info("Done.");
    }
}
