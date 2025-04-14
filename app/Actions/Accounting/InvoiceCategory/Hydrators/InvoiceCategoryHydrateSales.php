<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\InvoiceCategory\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class InvoiceCategoryHydrateSales implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;

    public string $jobQueue = 'urgent';


    public function getJobUniqueId(InvoiceCategory $invoiceCategory, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithInterval($invoiceCategory, $intervals, $doPreviousPeriods);
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
