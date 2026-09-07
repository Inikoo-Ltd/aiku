<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Goods\StockFamily;

use App\Helpers\TimeSeriesPeriodCalculator;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Goods\StockFamily;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoStockFamilyTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'long-low-priority';
    public string $commandSignature = 'stock-families:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = StockFamily::class;
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
                'key'   => 'invoice_transaction_has_stocks.stock_family_id',
                'date'  => 'invoice_transactions.date',
            ],
        ];
    }

    public function handle(?int $stockFamilyId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$stockFamilyId) {
            return;
        }

        $stockFamily = StockFamily::find($stockFamilyId);

        if (!$stockFamily) {
            return;
        }

        if (!$from || !$to) {
            $dateRange = $this->getDateRange($stockFamily->id);

            if (!$dateRange['from']) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange['from'])->toDateString();
            $to   = $to ?? Carbon::parse($dateRange['to'] ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            [$periodFrom, $periodTo] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

            if ($async) {
                ProcessStockFamilyTimeSeriesRecords::dispatch($stockFamily->id, $frequency, $periodFrom, $periodTo)->delay(300)->onQueue('sales_slave_historic');
            } else {
                ProcessStockFamilyTimeSeriesRecords::run($stockFamily->id, $frequency, $periodFrom, $periodTo);
            }
        }
    }


}
