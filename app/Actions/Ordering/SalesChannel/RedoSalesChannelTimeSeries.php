<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Ordering\SalesChannel;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Ordering\SalesChannel;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoSalesChannelTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'sales-channels:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(SalesChannel $salesChannel, bool $async = false): void
    {
        $firstInvoicedDate = DB::table('invoices')->where('sales_channel_id', $salesChannel->id)->whereNull('deleted_at')->min('date');
        $lastInvoicedDate  = DB::table('invoices')->where('sales_channel_id', $salesChannel->id)->whereNull('deleted_at')->max('date');

        if (!$firstInvoicedDate) {
            return;
        }

        $from = Carbon::parse($firstInvoicedDate)->toDateString();
        $to   = Carbon::parse($lastInvoicedDate ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessSalesChannelTimeSeriesRecords::dispatch($salesChannel->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessSalesChannelTimeSeriesRecords::run($salesChannel->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        SalesChannel::all()->each(function (SalesChannel $salesChannel) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessSalesChannelTimeSeriesRecords::run($salesChannel->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $salesChannels = SalesChannel::all();

        $bar = $command->getOutput()->createProgressBar($salesChannels->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($salesChannels as $salesChannel) {
            try {
                $this->handle($salesChannel, $async);
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
