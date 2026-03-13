<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Goods\TradeUnitFamily;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Goods\TradeUnitFamily;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoTradeUnitFamilyTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $jobQueue         = 'default-long';
    public string $commandSignature = 'trade-unit-families:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = TradeUnitFamily::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(TradeUnitFamily $tradeUnitFamily, bool $async = false, ?string $from = null, ?string $to = null): void
    {
        if (!$from || !$to) {
            $firstInvoicedDate = DB::table('invoice_transactions')
                ->join('invoice_transaction_has_trade_units', 'invoice_transaction_has_trade_units.invoice_transaction_id', '=', 'invoice_transactions.id')
                ->where('invoice_transaction_has_trade_units.trade_unit_family_id', $tradeUnitFamily->id)
                ->whereNull('invoice_transactions.deleted_at')
                ->min('invoice_transactions.date');
            $lastInvoicedDate = DB::table('invoice_transactions')
                ->join('invoice_transaction_has_trade_units', 'invoice_transaction_has_trade_units.invoice_transaction_id', '=', 'invoice_transactions.id')
                ->where('invoice_transaction_has_trade_units.trade_unit_family_id', $tradeUnitFamily->id)
                ->whereNull('invoice_transactions.deleted_at')
                ->max('invoice_transactions.date');

            if (!$firstInvoicedDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstInvoicedDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastInvoicedDate ?? now())->toDateString();
        }

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
        $tableName = (new $this->model())->getTable();
        $query     = DB::table($tableName)->select('id')->orderBy('id', 'desc');

        $query->chunk(1000, function (\Illuminate\Support\Collection $modelsData) use ($from, $to) {
            foreach ($modelsData as $modelId) {
                $model    = (new $this->model());
                $instance = $this->hasSoftDeletes($model)
                    ? $model->withTrashed()->find($modelId->id)
                    : $model->find($modelId->id);

                try {
                    $this->handle($instance, false, $from, $to);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());
        $tableName = (new $this->model())->getTable();
        $query     = $this->prepareQuery($tableName, $command);
        $count     = $query->count();
        $bar       = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        $query->chunk(1000, function (\Illuminate\Support\Collection $modelsData) use ($bar, $command) {
            foreach ($modelsData as $modelId) {
                $model    = (new $this->model());
                $instance = $this->hasSoftDeletes($model)
                    ? $model->withTrashed()->find($modelId->id)
                    : $model->find($modelId->id);

                try {
                    $this->handle($instance, (bool) $command->option('async'), $command->option('from'), $command->option('to'));
                } catch (Throwable $e) {
                    $command->error($e->getMessage());
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $command->info('');

        return 0;
    }
}
