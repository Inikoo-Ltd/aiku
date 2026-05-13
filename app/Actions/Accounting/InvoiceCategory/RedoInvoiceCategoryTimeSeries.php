<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceCategory;

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

    public string $jobQueue = 'default-long-slave';
    public string $commandSignature = 'invoice-categories:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

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
            $firstInvoicedDate = DB::connection('aiku_no_sticky')->table('invoices')->where('invoice_category_id', $invoiceCategory->id)->whereNull('deleted_at')->min('date');
            $lastInvoicedDate  = DB::connection('aiku_no_sticky')->table('invoices')->where('invoice_category_id', $invoiceCategory->id)->whereNull('deleted_at')->max('date');

            if (!$firstInvoicedDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstInvoicedDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastInvoicedDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessInvoiceCategoryTimeSeriesRecords::dispatch($invoiceCategory->id, $frequency, $from, $to)->onQueue('sales_slave_historic');
            } else {
                ProcessInvoiceCategoryTimeSeriesRecords::run($invoiceCategory->id, $frequency, $from, $to);
            }
        }
    }


}
