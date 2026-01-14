<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\MasterCollection\UI;

use App\Actions\Traits\WithTimeSeriesData;
use App\Models\Masters\MasterCollection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterCollectionTimeSeriesData
{
    use AsObject;
    use WithTimeSeriesData;

    public function handle(MasterCollection $masterCollection): array
    {
        $currency = $masterCollection->masterShop->group->currency->code;
        $timeSeriesRecordsTable = 'master_collection_time_series_records';

        // Get yearly and quarterly sales data
        $yearlySales = $this->getYearlySalesData($masterCollection, $timeSeriesRecordsTable);
        $quarterlySales = $this->getQuarterlySalesData($masterCollection, $timeSeriesRecordsTable);

        // Get total sales data
        $totalSalesData = $this->getTotalSalesData($masterCollection);

        // Get total customers from time series (customers_invoiced)
        $totalCustomers = $this->getTotalCustomersFromTimeSeries($masterCollection, $timeSeriesRecordsTable);

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
