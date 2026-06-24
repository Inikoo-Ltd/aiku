<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Goods\TradeUnitFamily;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Goods\TradeUnitFamily;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoTradeUnitFamilyTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'default-long-slave';
    public string $commandSignature = 'trade-unit-families:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = TradeUnitFamily::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_$to";
    }

    public function handle(?int $tradeUnitFamilyId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$tradeUnitFamilyId) {
            return;
        }

        $tradeUnitFamily = TradeUnitFamily::find($tradeUnitFamilyId);

        if (!$tradeUnitFamily) {
            return;
        }

        if (!$from || !$to) {
            $dateRange = DB::connection('aiku_no_sticky')->table('invoice_transactions')
                ->join('invoice_transaction_has_trade_units', 'invoice_transaction_has_trade_units.invoice_transaction_id', '=', 'invoice_transactions.id')
                ->where('invoice_transaction_has_trade_units.trade_unit_family_id', $tradeUnitFamily->id)
                ->whereNull('invoice_transactions.deleted_at')
                ->selectRaw('MIN(invoice_transactions.date) as first_date, MAX(invoice_transactions.date) as last_date')
                ->first();

            if (!$dateRange?->first_date) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange->first_date)->toDateString();
            $to   = $to ?? Carbon::parse($dateRange->last_date ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessTradeUnitFamilyTimeSeriesRecords::dispatch($tradeUnitFamily->id, $frequency, $from, $to)->onQueue('sales_slave_historic');
            } else {
                ProcessTradeUnitFamilyTimeSeriesRecords::run($tradeUnitFamily->id, $frequency, $from, $to);
            }
        }
    }


}
