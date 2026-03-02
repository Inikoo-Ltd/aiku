<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Goods\TradeUnitFamily;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Goods\TradeUnitFamily;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoTradeUnitFamilyTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'trade-unit-families:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(TradeUnitFamily $tradeUnitFamily, bool $async = false): void
    {
        $firstInvoicedDate = DB::table('invoice_transaction_has_trade_units')->where('trade_unit_family_id', $tradeUnitFamily->id)->min('date');
        $lastInvoicedDate  = DB::table('invoice_transaction_has_trade_units')->where('trade_unit_family_id', $tradeUnitFamily->id)->max('date');

        if (!$firstInvoicedDate) {
            return;
        }

        $from = Carbon::parse($firstInvoicedDate)->toDateString();
        $to   = Carbon::parse($lastInvoicedDate ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessTradeUnitFamilyTimeSeriesRecords::dispatch($tradeUnitFamily->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessTradeUnitFamilyTimeSeriesRecords::run($tradeUnitFamily->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        TradeUnitFamily::all()->each(function (TradeUnitFamily $tradeUnitFamily) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessTradeUnitFamilyTimeSeriesRecords::run($tradeUnitFamily->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $tradeUnitFamilies = TradeUnitFamily::all();

        $bar = $command->getOutput()->createProgressBar($tradeUnitFamilies->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($tradeUnitFamilies as $tradeUnitFamily) {
            try {
                $this->handle($tradeUnitFamily, $async);
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
