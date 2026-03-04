<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 04 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStock;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoOrgStockTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'org-stocks:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(OrgStock $orgStock, bool $async = false): void
    {
        $firstInvoicedDate = DB::table('invoice_transaction_has_org_stocks')->where('org_stock_id', $orgStock->id)->min('date');
        $lastInvoicedDate  = DB::table('invoice_transaction_has_org_stocks')->where('org_stock_id', $orgStock->id)->max('date');

        if (!$firstInvoicedDate) {
            return;
        }

        $from = Carbon::parse($firstInvoicedDate)->toDateString();
        $to   = Carbon::parse($lastInvoicedDate ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessOrgStockTimeSeriesRecords::dispatch($orgStock->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessOrgStockTimeSeriesRecords::run($orgStock->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        OrgStock::all()->each(function (OrgStock $orgStock) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessOrgStockTimeSeriesRecords::run($orgStock->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $orgStocks = OrgStock::all();

        $bar = $command->getOutput()->createProgressBar($orgStocks->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($orgStocks as $orgStock) {
            try {
                $this->handle($orgStock, $async);
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
