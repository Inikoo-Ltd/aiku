<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\Traits\WithTimeSeriesData;
use App\Models\Catalogue\Collection;
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

        // Get total customers from time series (customers_invoiced)
        $totalCustomers = $this->getTotalCustomersFromTimeSeries($collection, $timeSeriesRecordsTable);

        return [
            'all_sales_since' => $totalSalesData['all_sales_since'],
            'total_sales' => $totalSalesData['total_sales'],
            'total_invoices' => $totalSalesData['total_invoices'],
            'total_customers' => $totalCustomers,
            'yearly_sales' => $yearlySales,
            'quarterly_sales' => $quarterlySales,
            'currency' => $currency,
        ];
    }
}
