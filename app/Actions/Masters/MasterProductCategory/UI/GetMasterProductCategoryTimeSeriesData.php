<?php

/*
 * Author: Nickel <nickel@gemini.com>
 * Copyright (c) 2026, Nickel
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Traits\WithTimeSeriesData;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterProductCategoryTimeSeriesData
{
    use AsObject;
    use WithTimeSeriesData;

    public function handle(MasterProductCategory $masterProductCategory): array
    {
        $currency = $masterProductCategory->group->currency->code;
        $timeSeriesRecordsTable = 'master_product_category_time_series_records';

        // Get yearly and quarterly sales data
        $yearlySales = $this->getYearlySalesData($masterProductCategory, $timeSeriesRecordsTable);
        $quarterlySales = $this->getQuarterlySalesData($masterProductCategory, $timeSeriesRecordsTable);

        // Get total sales data
        $totalSalesData = $this->getTotalSalesData($masterProductCategory);

        // Get total customers from time series (customers_invoiced)
        $totalCustomers = $this->getTotalCustomersFromTimeSeries($masterProductCategory, $timeSeriesRecordsTable);

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
