<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Mon, 01 Dec 2025 16:43:14 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\ProductCategory;
use DB;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductCategoryHydrateInvoiceIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;

    public function getJobUniqueId(ProductCategory $productCategory): string
    {
        return $productCategory->id;
    }

    public function handle(ProductCategory $productCategory, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $stats = [];

        $invoiceIdsQuery = DB::table('invoice_transactions as it')
            ->join('transactions as t', 'it.transaction_id', '=', 't.id')
            ->join('products as p', function ($join) {
                $join->on('t.model_id', '=', 'p.id')
                    ->where('t.model_type', '=', 'Product');
            })
            ->where(function ($q) use ($productCategory) {
                $q->where('p.family_id', $productCategory->id)
                    ->orWhere('p.sub_department_id', $productCategory->id)
                    ->orWhere('p.department_id', $productCategory->id);
            })
            ->select('it.invoice_id')
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
