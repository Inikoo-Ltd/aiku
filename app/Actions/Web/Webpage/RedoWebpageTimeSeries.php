<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Webpage;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoWebpageTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'webpages:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(Webpage $webpage, bool $async = false): void
    {
        $firstViewDate = DB::table('website_page_views')->where('webpage_id', $webpage->id)->min('view_date');
        $lastViewDate  = DB::table('website_page_views')->where('webpage_id', $webpage->id)->max('view_date');

        if (!$firstViewDate) {
            return;
        }

        $from = Carbon::parse($firstViewDate)->toDateString();
        $to   = Carbon::parse($lastViewDate ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessWebpageTimeSeriesRecords::dispatch($webpage->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessWebpageTimeSeriesRecords::run($webpage->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        Webpage::all()->each(function (Webpage $webpage) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessWebpageTimeSeriesRecords::run($webpage->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $webpages = Webpage::all();

        $bar = $command->getOutput()->createProgressBar($webpages->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($webpages as $webpage) {
            try {
                $this->handle($webpage, $async);
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
