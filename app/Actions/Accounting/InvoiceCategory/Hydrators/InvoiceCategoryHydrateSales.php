<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\InvoiceCategory\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceCategory;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class InvoiceCategoryHydrateSales
{
    use AsAction;
    use WithIntervalsAggregators;

    public string $jobQueue = 'sales';

    private InvoiceCategory $invoiceCategory;

    public function __construct(InvoiceCategory $invoiceCategory)
    {
        $this->invoiceCategory = $invoiceCategory;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->invoiceCategory->id))->dontRelease()];
    }

    public function handle(InvoiceCategory $invoiceCategory, ?array $intervals = null, $doPreviousPeriods = null): void
    {
        $stats = [];

        $queryBase = Invoice::where('in_process', false)->where('invoice_category_id', $invoiceCategory->id)->selectRaw('sum(net_amount) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'sales_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBase = Invoice::where('in_process', false)->where('invoice_category_id', $invoiceCategory->id)->selectRaw('sum(grp_net_amount) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'sales_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBase = Invoice::where('in_process', false)->where('invoice_category_id', $invoiceCategory->id)->selectRaw('sum(org_net_amount) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'sales_org_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );
        $invoiceCategory->salesIntervals->update($stats);
    }


}
