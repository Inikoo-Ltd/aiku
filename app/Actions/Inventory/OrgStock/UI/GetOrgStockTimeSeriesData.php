<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 16 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Traits\WithTimeSeriesData;
use App\Models\Inventory\OrgStock;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgStockTimeSeriesData
{
    use AsObject;
    use WithTimeSeriesData;

    public function handle(OrgStock $orgStock): array
    {
        $currency = $orgStock->organisation->currency->code;
        $timeSeriesRecordsTable = 'org_stock_time_series_records';

        $yearlySales = $this->getYearlySalesData($orgStock, $timeSeriesRecordsTable);
        $quarterlySales = $this->getQuarterlySalesData($orgStock, $timeSeriesRecordsTable);

        $totalSalesData = $this->getTotalSalesData($orgStock);

        $totalCustomers = $this->getTotalCustomersFromTimeSeries($orgStock, $timeSeriesRecordsTable);

        return [
            'all_sales_since' => $totalSalesData['all_sales_since'],
            'total_sales'     => $totalSalesData['total_sales'],
            'total_invoices'  => $totalSalesData['total_invoices'],
            'total_customers' => $totalCustomers,
            'yearly_sales'    => $yearlySales,
            'quarterly_sales' => $quarterlySales,
            'currency'        => $currency,
        ];
    }
}
