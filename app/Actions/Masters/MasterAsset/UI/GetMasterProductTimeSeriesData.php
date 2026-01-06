<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Traits\WithTimeSeriesData;
use App\Models\Masters\MasterAsset;
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

        // Get total customers from time series (customers_invoiced)
        $totalCustomers = $this->getTotalCustomersFromTimeSeries($masterAsset, $timeSeriesRecordsTable);

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
