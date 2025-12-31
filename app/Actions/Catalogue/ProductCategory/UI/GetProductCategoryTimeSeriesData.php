<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Traits\WithTimeSeriesData;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetProductCategoryTimeSeriesData
{
    use AsObject;
    use WithTimeSeriesData;

    public function handle(ProductCategory $productCategory): array
    {
        $currency = $productCategory->shop->currency->code;
        $timeSeriesRecordsTable = 'product_category_time_series_records';

        // Get yearly and quarterly sales data
        $yearlySales = $this->getYearlySalesData($productCategory, $timeSeriesRecordsTable);
        $quarterlySales = $this->getQuarterlySalesData($productCategory, $timeSeriesRecordsTable);

        // Get total sales data
        $totalSalesData = $this->getTotalSalesData($productCategory);

        // Get customer metrics
        $customerMetrics = $this->getCustomerMetrics($productCategory, function ($productCategory) {
            $categoryColumn = $this->getCategoryColumn($productCategory);

            return DB::table('invoice_transactions')
                ->join('invoices', 'invoice_transactions.invoice_id', '=', 'invoices.id')
                ->where('invoice_transactions.' . $categoryColumn, $productCategory->id)
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

    private function getCategoryColumn(ProductCategory $productCategory): string
    {
        return match ($productCategory->type->value) {
            'department' => 'department_id',
            'sub-department' => 'sub_department_id',
            'family' => 'family_id',
            default => 'department_id',
        };
    }
}
