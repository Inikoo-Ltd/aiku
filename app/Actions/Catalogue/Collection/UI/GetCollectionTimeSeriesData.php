<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\Traits\WithTimeSeriesData;
use App\Models\Catalogue\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCollectionTimeSeriesData
{
    use AsObject;
    use WithTimeSeriesData;

    public function handle(Collection $collection): array
    {
        $currency = $collection->shop->currency->code;
        $timeSeriesRecordsTable = 'collection_time_series_records';

        // Get yearly and quarterly sales data
        $yearlySales = $this->getYearlySalesData($collection, $timeSeriesRecordsTable);
        $quarterlySales = $this->getQuarterlySalesData($collection, $timeSeriesRecordsTable);

        // Get total sales data
        $totalSalesData = $this->getTotalSalesData($collection);

        // Get customer metrics
        $customerMetrics = $this->getCustomerMetrics($collection, function ($collection) {
            return DB::table('invoice_transactions')
                ->join('invoices', 'invoice_transactions.invoice_id', '=', 'invoices.id')
                ->where('invoice_transactions.collection_id', $collection->id)
                ->where('invoices.in_process', false)
                ->select('invoices.customer_id')
                ->groupBy('invoices.customer_id')
                ->havingRaw('COUNT(DISTINCT invoices.id) > 1')
                ->get()
                ->count();
        });

        return [
            'all_sales_since' => $totalSalesData['all_sales_since'],
            'total_sales' => $totalSalesData['total_sales'],
            'total_invoices' => $totalSalesData['total_invoices'],
            'customer_metrics' => $customerMetrics,
            'yearly_sales' => $yearlySales,
            'quarterly_sales' => $quarterlySales,
            'currency' => $currency,
        ];
    }
}
