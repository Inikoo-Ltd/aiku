<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Goods\Stock;

use App\Helpers\TimeSeriesPeriodCalculator;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Goods\Stock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoStockTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'long-low-priority';
    public string $commandSignature = 'stocks:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Stock::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_$to";
    }

    protected function dateRangeSources(): array
    {
        return [
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('invoice_transactions')
                    ->join('invoice_transaction_has_stocks', 'invoice_transaction_has_stocks.invoice_transaction_id', '=', 'invoice_transactions.id')
                    ->whereNull('invoice_transactions.deleted_at'),
                'key'   => 'invoice_transaction_has_stocks.stock_id',
                'date'  => 'invoice_transactions.date',
            ],
        ];
    }

    public function handle(?int $stockId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$stockId) {
            return;
        }

        $stock = Stock::find($stockId);

        if (!$stock) {
            return;
        }

        if (!$from || !$to) {
            $dateRange = $this->getDateRange($stock->id);

            if (!$dateRange['from']) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange['from'])->toDateString();
            $to   = $to ?? Carbon::parse($dateRange['to'] ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            [$periodFrom, $periodTo] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

            if ($async) {
                ProcessStockTimeSeriesRecords::dispatch($stock->id, $frequency, $periodFrom, $periodTo)->onQueue('sales_slave_historic');
            } else {
                ProcessStockTimeSeriesRecords::run($stock->id, $frequency, $periodFrom, $periodTo);
            }
        }
    }

}
