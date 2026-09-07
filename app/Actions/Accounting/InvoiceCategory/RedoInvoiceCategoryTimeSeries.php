<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceCategory;

use App\Helpers\TimeSeriesPeriodCalculator;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Accounting\InvoiceCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoInvoiceCategoryTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'long-low-priority';
    public string $commandSignature = 'invoice-categories:redo_time_series {--O|organisation= : Organisation slug} {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = InvoiceCategory::class;
    }

    public function getJobUniqueId(?int $invoiceCategoryId, string $from, string $to): string
    {
        if ($invoiceCategoryId == null) {
            $invoiceCategoryId = 'empty';
        }

        return $invoiceCategoryId.":{$from}_$to";
    }

    protected function dateRangeSources(): array
    {
        return [
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('invoices')->whereNull('deleted_at'),
                'key'   => 'invoice_category_id',
                'date'  => 'date',
            ],
        ];
    }

    public function handle(?int $invoiceCategoryId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$invoiceCategoryId) {
            return;
        }

        $invoiceCategory = InvoiceCategory::find($invoiceCategoryId);

        if (!$invoiceCategory) {
            return;
        }

        if (!$from || !$to) {
            $dateRange = $this->getDateRange($invoiceCategory->id);

            if (!$dateRange['from']) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange['from'])->toDateString();
            $to   = $to ?? Carbon::parse($dateRange['to'] ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            [$periodFrom, $periodTo] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

            if ($async) {
                ProcessInvoiceCategoryTimeSeriesRecords::dispatch($invoiceCategory->id, $frequency, $periodFrom, $periodTo)->onQueue('sales_slave_historic');
            } else {
                ProcessInvoiceCategoryTimeSeriesRecords::run($invoiceCategory->id, $frequency, $periodFrom, $periodTo);
            }
        }
    }
}
