<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Mar 2025 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockFamily\UI;

use App\Actions\OrgAction;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\OrgStockFamilyTimeSeriesRecord;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;

class IndexOrgStockFamilyTimeSeries extends OrgAction
{
    public function handle(OrgStockFamily $orgStockFamily, string|null $prefix): LengthAwarePaginator
    {
        $frequency = request()->input('frequency', TimeSeriesFrequencyEnum::MONTHLY->value);
        $frequencyEnum = TimeSeriesFrequencyEnum::tryFrom($frequency) ?? TimeSeriesFrequencyEnum::MONTHLY;

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $timeSeries = $orgStockFamily->timeSeries()
            ->where('frequency', $frequencyEnum)
            ->first();

        if (!$timeSeries) {
            return new LengthAwarePaginator([], 0, 20);
        }

        return QueryBuilder::for(OrgStockFamilyTimeSeriesRecord::class)
            ->where('org_stock_family_time_series_id', $timeSeries->id)
            ->select([
                'id',
                'from',
                'to',
                'sales_org_currency_external',
                'sales_grp_currency_external',
                'invoices',
                'refunds',
                'orders',
                'customers_invoiced',
            ])
            ->selectRaw('? as currency_code', [$orgStockFamily->organisation->currency->code])
            ->selectSub(
                OrgStockFamilyTimeSeriesRecord::query()
                    ->from('org_stock_family_time_series_records as last_year_records')
                    ->select('last_year_records.sales_org_currency_external')
                    ->whereColumn('last_year_records.org_stock_family_time_series_id', 'org_stock_family_time_series_records.org_stock_family_time_series_id')
                    ->whereRaw('org_stock_family_time_series_records."from" - interval \'1 year\' between last_year_records."from" and last_year_records."to"')
                    ->limit(1),
                'sales_org_currency_external_ly'
            )
            ->defaultSort('-from')
            ->allowedSorts(['from', 'to', 'sales_org_currency_external', 'invoices', 'refunds', 'customers_invoiced'])
            ->allowedFilters([])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(string|null $prefix): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withEmptyState(
                    [
                        'title'       => __('No sales data'),
                        'description' => __('No sales records found for this period'),
                    ]
                )
                ->withFrequency()
                ->column('period', __('Period'), canBeHidden: false, sortable: false)
                ->column('sales_org_currency_external', __('Sales'), canBeHidden: false, sortable: true, type: 'number')
                ->column('sales_org_currency_external_delta', __('Δ 1Y'), canBeHidden: false, sortable: false, align: 'right')
                ->column('invoices', __('Invoices'), canBeHidden: false, sortable: true, type: 'number')
                ->column('refunds', __('Refunds'), canBeHidden: false, sortable: true, type: 'number')
                ->column('customers_invoiced', __('Customers'), canBeHidden: false, sortable: true, type: 'number')
                ->defaultSort('-from');
        };
    }
}
