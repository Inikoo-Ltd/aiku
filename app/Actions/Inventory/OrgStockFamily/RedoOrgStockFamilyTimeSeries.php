<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 04 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStockFamily;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Inventory\OrgStockFamily;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoOrgStockFamilyTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'default-long-slave';
    public string $commandSignature = 'org-stock-families:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = OrgStockFamily::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_$to";
    }

    public function handle(?int $orgStockFamilyId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$orgStockFamilyId) {
            return;
        }

        $orgStockFamily = OrgStockFamily::find($orgStockFamilyId);

        if (!$orgStockFamily) {
            return;
        }

        if (!$from || !$to) {
            $dateRange = DB::connection('aiku_no_sticky')->table('invoice_transactions')
                ->join('invoice_transaction_has_org_stocks', 'invoice_transaction_has_org_stocks.invoice_transaction_id', '=', 'invoice_transactions.id')
                ->where('invoice_transaction_has_org_stocks.org_stock_family_id', $orgStockFamily->id)
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
                ProcessOrgStockFamilyTimeSeriesRecords::dispatch($orgStockFamily->id, $frequency, $from, $to)->onQueue('sales_slave_historic');
            } else {
                ProcessOrgStockFamilyTimeSeriesRecords::run($orgStockFamily->id, $frequency, $from, $to);
            }
        }
    }


}
