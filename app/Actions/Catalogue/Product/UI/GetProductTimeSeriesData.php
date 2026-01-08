<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Traits\WithTimeSeriesData;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\Concerns\AsObject;

class GetProductTimeSeriesData
{
    use AsObject;
    use WithTimeSeriesData;

    public function handle(Product $product): array
    {
        // Product uses Asset for time series
        if (!$product->asset) {
            return $this->emptyTimeSeriesResponse($product->shop->currency->code);
        }

        $currency = $product->shop->currency->code;
        $timeSeriesRecordsTable = 'asset_time_series_records';

        // Get yearly and quarterly sales data
        $yearlySales = $this->getYearlySalesData($product->asset, $timeSeriesRecordsTable);
        $quarterlySales = $this->getQuarterlySalesData($product->asset, $timeSeriesRecordsTable);

        // Get total sales data
        $totalSalesData = $this->getTotalSalesData($product->asset);

        // Get total customers from time series (customers_invoiced)
        $totalCustomers = $this->getTotalCustomersFromTimeSeries($product->asset, $timeSeriesRecordsTable);

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
