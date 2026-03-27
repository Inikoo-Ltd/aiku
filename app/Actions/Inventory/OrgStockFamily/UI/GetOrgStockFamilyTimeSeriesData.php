<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Mar 2025 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockFamily\UI;

use App\Actions\Traits\WithTimeSeriesData;
use App\Models\Inventory\OrgStockFamily;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgStockFamilyTimeSeriesData
{
    use AsObject;
    use WithTimeSeriesData;

    public function handle(OrgStockFamily $orgStockFamily): array
    {
        $currency = $orgStockFamily->organisation->currency->code;
        $timeSeriesRecordsTable = 'org_stock_family_time_series_records';

        $yearlySales = $this->getYearlySalesData($orgStockFamily, $timeSeriesRecordsTable);
        $quarterlySales = $this->getQuarterlySalesData($orgStockFamily, $timeSeriesRecordsTable);
        $totalSalesData = $this->getTotalSalesData($orgStockFamily);
        $totalCustomers = $this->getTotalCustomersFromTimeSeries($orgStockFamily, $timeSeriesRecordsTable);

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
