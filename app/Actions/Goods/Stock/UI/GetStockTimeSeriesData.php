<?php

/*
 * Author Louis Perez
 * Created on 28-09-2026-14h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\Stock\UI;

use App\Actions\Traits\WithTimeSeriesData;
use App\Models\Goods\Stock;
use Lorisleiva\Actions\Concerns\AsObject;

class GetStockTimeSeriesData
{
    use AsObject;
    use WithTimeSeriesData;

    public function handle(Stock $stock): array
    {
        $currency = $stock->group->currency->code;

        if (!$stock->timeSeries()->exists()) {
            return $this->emptyTimeSeriesResponse($currency);
        }

        $timeSeriesRecordsTable = 'stock_time_series_records';

        $totalSalesData = $this->getTotalSalesData($stock);

        return [
            'all_sales_since' => $totalSalesData['all_sales_since'],
            'total_sales'     => $totalSalesData['total_sales'],
            'total_invoices'  => $totalSalesData['total_invoices'],
            'total_customers' => $this->getTotalCustomersFromTimeSeries($stock, $timeSeriesRecordsTable),
            'yearly_sales'    => $this->getYearlySalesData($stock, $timeSeriesRecordsTable),
            'quarterly_sales' => $this->getQuarterlySalesData($stock, $timeSeriesRecordsTable),
            'currency'        => $currency,
        ];
    }
}
