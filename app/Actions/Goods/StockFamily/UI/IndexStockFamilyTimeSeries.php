<?php

/*
 * Author Louis Perez
 * Created on 29-09-2026-13h-38m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\StockFamily\UI;

use App\Actions\OrgAction;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\StockFamily;
use App\Models\Goods\StockFamilyTimeSeriesRecord;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;

class IndexStockFamilyTimeSeries extends OrgAction
{
    public function handle(StockFamily $stockFamily, ?string $prefix = null): LengthAwarePaginator
    {
        $frequency     = request()->input('frequency', TimeSeriesFrequencyEnum::MONTHLY->value);
        $frequencyEnum = TimeSeriesFrequencyEnum::tryFrom($frequency) ?? TimeSeriesFrequencyEnum::MONTHLY;

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $timeSeries = $stockFamily->timeSeries()
            ->where('frequency', $frequencyEnum)
            ->first();

        if (!$timeSeries) {
            return new LengthAwarePaginator([], 0, 20);
        }

        return QueryBuilder::for(StockFamilyTimeSeriesRecord::class)
            ->where('stock_family_time_series_id', $timeSeries->id)
            ->select([
                'id',
                'from',
                'to',
                'sales_grp_currency_external',
                'invoices',
                'refunds',
                'orders',
                'customers_invoiced',
            ])
            ->selectRaw('? as currency_code', [$stockFamily->group->currency->code])
            ->selectSub(
                StockFamilyTimeSeriesRecord::query()
                    ->from('stock_family_time_series_records as last_year_records')
                    ->select('last_year_records.sales_grp_currency_external')
                    ->whereColumn('last_year_records.stock_family_time_series_id', 'stock_family_time_series_records.stock_family_time_series_id')
                    ->whereRaw('stock_family_time_series_records."from" - interval \'1 year\' between last_year_records."from" and last_year_records."to"')
                    ->limit(1),
                'sales_grp_currency_external_ly'
            )
            ->defaultSort('-from')
            ->allowedSorts(['from', 'to', 'sales_grp_currency_external', 'invoices', 'refunds', 'customers_invoiced'])
            ->allowedFilters([])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
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
                ->column('sales_grp_currency_external', __('Sales'), canBeHidden: false, sortable: true, type: 'number')
                ->column('sales_grp_currency_external_delta', __('Δ 1Y'), canBeHidden: false, sortable: false, align: 'right')
                ->column('invoices', __('Invoices'), canBeHidden: false, sortable: true, type: 'number')
                ->column('refunds', __('Refunds'), canBeHidden: false, sortable: true, type: 'number')
                ->column('customers_invoiced', __('Customers'), canBeHidden: false, sortable: true, type: 'number')
                ->defaultSort('-from');
        };
    }
}
