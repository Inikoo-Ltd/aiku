<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoWebsiteTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'websites:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(Website $website, bool $async = false): void
    {
        $firstVisitDate = DB::table('website_visitors')->where('website_id', $website->id)->min('first_seen_at');
        $lastVisitDate  = DB::table('website_visitors')->where('website_id', $website->id)->max('first_seen_at');

        if (!$firstVisitDate) {
            return;
        }

        $from = Carbon::parse($firstVisitDate)->toDateString();
        $to   = Carbon::parse($lastVisitDate ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessWebsiteTimeSeriesRecords::dispatch($website->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessWebsiteTimeSeriesRecords::run($website->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        Website::all()->each(function (Website $website) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessWebsiteTimeSeriesRecords::run($website->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $websites = Website::all();

        $bar = $command->getOutput()->createProgressBar($websites->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($websites as $website) {
            try {
                $this->handle($website, $async);
            } catch (Throwable $e) {
                $command->error($e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $command->info('');

        return 0;
    }
}
