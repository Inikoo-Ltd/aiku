<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceCategory;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Accounting\InvoiceCategory;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoInvoiceCategoryTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'invoice-categories:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(InvoiceCategory $invoiceCategory, bool $async = false): void
    {
        $firstInvoicedDate = DB::table('invoices')->where('invoice_category_id', $invoiceCategory->id)->whereNull('deleted_at')->min('date');
        $lastInvoicedDate  = DB::table('invoices')->where('invoice_category_id', $invoiceCategory->id)->whereNull('deleted_at')->max('date');

        if (!$firstInvoicedDate) {
            return;
        }

        $from = Carbon::parse($firstInvoicedDate)->toDateString();
        $to   = Carbon::parse($lastInvoicedDate ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessInvoiceCategoryTimeSeriesRecords::dispatch($invoiceCategory->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessInvoiceCategoryTimeSeriesRecords::run($invoiceCategory->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        InvoiceCategory::all()->each(function (InvoiceCategory $invoiceCategory) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessInvoiceCategoryTimeSeriesRecords::run($invoiceCategory->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $invoiceCategories = InvoiceCategory::all();

        $bar = $command->getOutput()->createProgressBar($invoiceCategories->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($invoiceCategories as $invoiceCategory) {
            try {
                $this->handle($invoiceCategory, $async);
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
