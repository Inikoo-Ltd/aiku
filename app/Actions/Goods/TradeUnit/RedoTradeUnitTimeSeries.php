<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Goods\TradeUnit;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Goods\TradeUnit;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoTradeUnitTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'trade-units:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(TradeUnit $tradeUnit, bool $async = false): void
    {
        $firstInvoicedDate = DB::table('invoice_transaction_has_trade_units')->where('trade_unit_id', $tradeUnit->id)->min('date');
        $lastInvoicedDate  = DB::table('invoice_transaction_has_trade_units')->where('trade_unit_id', $tradeUnit->id)->max('date');

        if (!$firstInvoicedDate) {
            return;
        }

        $from = Carbon::parse($firstInvoicedDate)->toDateString();
        $to   = Carbon::parse($lastInvoicedDate ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessTradeUnitTimeSeriesRecords::dispatch($tradeUnit->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessTradeUnitTimeSeriesRecords::run($tradeUnit->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        TradeUnit::all()->each(function (TradeUnit $tradeUnit) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessTradeUnitTimeSeriesRecords::run($tradeUnit->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $tradeUnits = TradeUnit::all();

        $bar = $command->getOutput()->createProgressBar($tradeUnits->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($tradeUnits as $tradeUnit) {
            try {
                $this->handle($tradeUnit, $async);
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
