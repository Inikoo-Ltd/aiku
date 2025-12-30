<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Traits\WithTimeSeriesData;
use App\Models\Catalogue\Product;
use Illuminate\Support\Facades\DB;
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

        // Get customer metrics
        $customerMetrics = $this->getCustomerMetrics($product, function ($product) {
            return DB::table('invoice_transactions')
                ->join('invoices', 'invoice_transactions.invoice_id', '=', 'invoices.id')
                ->where('invoice_transactions.model_type', get_class($product))
                ->where('invoice_transactions.model_id', $product->id)
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
