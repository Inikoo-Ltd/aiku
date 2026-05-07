<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 04 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoOrgStockTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue         = 'default-long-slave';
    public string $commandSignature = 'org-stocks:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = OrgStock::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(?int $orgStockId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$orgStockId) {
            return;
        }

        $orgStock = OrgStock::find($orgStockId);

        if (!$orgStock) {
            return;
        }

        if (!$from || !$to) {
            $dateRange = DB::connection('aiku_no_sticky')->table('invoice_transactions')
                ->join('invoice_transaction_has_org_stocks', 'invoice_transaction_has_org_stocks.invoice_transaction_id', '=', 'invoice_transactions.id')
                ->where('invoice_transaction_has_org_stocks.org_stock_id', $orgStock->id)
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
                ProcessOrgStockTimeSeriesRecords::dispatch($orgStock->id, $frequency, $from, $to)->delay(300);
            } else {
                ProcessOrgStockTimeSeriesRecords::run($orgStock->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        $tableName = (new $this->model())->getTable();
        $query     = DB::table($tableName)->select('id')->orderBy('id', 'desc');

        $query->chunk(1000, function (Collection $modelsData) use ($from, $to) {
            foreach ($modelsData as $modelId) {
                try {
                    $this->handle($modelId->id, $from, $to, false);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });
    }
}
