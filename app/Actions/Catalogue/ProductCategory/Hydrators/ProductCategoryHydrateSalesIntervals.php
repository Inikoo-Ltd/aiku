<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Tue, 02 Dec 2025 10:05:55 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\ProductCategory;
use DB;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductCategoryHydrateSalesIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalUniqueJob;
    use WithIntervalsAggregators;

    public function getJobUniqueId(ProductCategory $productCategory, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithIntervalFromId($productCategory->id, $intervals, $doPreviousPeriods);
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

        $queryBaseSales = Invoice::query()
            ->whereIn('id', $invoiceIdsQuery)
            ->where('in_process', false)
            ->selectRaw('sum(net_amount) as sum_aggregate');

        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBaseSales,
            statField: 'sales_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBaseSalesGrpCurrency = Invoice::query()
            ->whereIn('id', $invoiceIdsQuery)
            ->where('in_process', false)
            ->selectRaw('sum(grp_net_amount) as sum_aggregate');

        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBaseSalesGrpCurrency,
            statField: 'sales_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBaseSalesOrgCurrency = Invoice::query()
            ->whereIn('id', $invoiceIdsQuery)
            ->where('in_process', false)
            ->selectRaw('sum(org_net_amount) as sum_aggregate');

        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBaseSalesOrgCurrency,
            statField: 'sales_org_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $productCategory->salesIntervals()->update($stats);
    }
}
