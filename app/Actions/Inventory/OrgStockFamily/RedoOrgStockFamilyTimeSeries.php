<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 04 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStockFamily;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Inventory\OrgStockFamily;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoOrgStockFamilyTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'org-stock-families:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(OrgStockFamily $orgStockFamily, bool $async = false): void
    {
        $firstInvoicedDate = DB::table('invoice_transaction_has_org_stocks')->where('org_stock_family_id', $orgStockFamily->id)->min('date');
        $lastInvoicedDate  = DB::table('invoice_transaction_has_org_stocks')->where('org_stock_family_id', $orgStockFamily->id)->max('date');

        if (!$firstInvoicedDate) {
            return;
        }

        $from = Carbon::parse($firstInvoicedDate)->toDateString();
        $to   = Carbon::parse($lastInvoicedDate ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessOrgStockFamilyTimeSeriesRecords::dispatch($orgStockFamily->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessOrgStockFamilyTimeSeriesRecords::run($orgStockFamily->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        OrgStockFamily::all()->each(function (OrgStockFamily $orgStockFamily) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessOrgStockFamilyTimeSeriesRecords::run($orgStockFamily->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $orgStockFamilies = OrgStockFamily::all();

        $bar = $command->getOutput()->createProgressBar($orgStockFamilies->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($orgStockFamilies as $orgStockFamily) {
            try {
                $this->handle($orgStockFamily, $async);
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
