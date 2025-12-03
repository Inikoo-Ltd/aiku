<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Mon, 01 Dec 2025 16:43:14 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\ProductCategory;
use DB;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductCategoryHydrateInvoiceIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalUniqueJob;
    use WithIntervalsAggregators;

    public function getJobUniqueId(int $productCategoryId, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithIntervalFromId($productCategoryId, $intervals, $doPreviousPeriods);
    }

    public function handle(int $productCategoryId, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $productCategory = ProductCategory::find($productCategoryId);

        if (!$productCategory) {
            return;
        }

        $stats = [];

        $fieldName = match ($productCategory->type) {
            ProductCategoryTypeEnum::DEPARTMENT => 'department_id',
            ProductCategoryTypeEnum::FAMILY => 'family_id',
            ProductCategoryTypeEnum::SUB_DEPARTMENT => 'sub_department_id',
        };

        $invoiceIdsQuery = DB::table('invoice_transactions')
            ->where($fieldName, $productCategory->id)
            ->select('invoice_id')
            ->distinct();

        $queryBaseInvoice = Invoice::query()
            ->whereIn('id', $invoiceIdsQuery)
            ->where('in_process', false)
            ->where('type', InvoiceTypeEnum::INVOICE)
            ->selectRaw('count(*) as sum_aggregate');

        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBaseInvoice,
            statField: 'invoices_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBaseRefund = Invoice::query()
            ->whereIn('id', $invoiceIdsQuery)
            ->where('in_process', false)
            ->where('type', InvoiceTypeEnum::REFUND)
            ->selectRaw('count(*) as sum_aggregate');

        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBaseRefund,
            statField: 'refunds_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $productCategory->orderingIntervals()->update($stats);
    }
}
