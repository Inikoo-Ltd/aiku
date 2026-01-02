<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Traits\WithTimeSeriesData;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterProductTimeSeriesData
{
    use AsObject;
    use WithTimeSeriesData;

    public function handle(MasterAsset $masterAsset): array
    {
        if (!$masterAsset->timeSeries()->exists()) {
            return $this->emptyTimeSeriesResponse($masterAsset->group->currency->code);
        }

        $currency = $masterAsset->group->currency->code;
        $timeSeriesRecordsTable = 'master_asset_time_series_records';

        $yearlySales = $this->getYearlySalesData($masterAsset, $timeSeriesRecordsTable);
        $quarterlySales = $this->getQuarterlySalesData($masterAsset, $timeSeriesRecordsTable);

        $totalSalesData = $this->getTotalSalesData($masterAsset);

        $customerMetrics = $this->getCustomerMetrics($masterAsset, function ($masterAsset) {
            $productIds = $masterAsset->products()->pluck('id')->toArray();

            if (empty($productIds)) {
                return 0;
            }

            return DB::table('invoice_transactions')
                ->join('invoices', 'invoice_transactions.invoice_id', '=', 'invoices.id')
                ->where('invoice_transactions.model_type', 'Product')
                ->whereIn('invoice_transactions.model_id', $productIds)
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
