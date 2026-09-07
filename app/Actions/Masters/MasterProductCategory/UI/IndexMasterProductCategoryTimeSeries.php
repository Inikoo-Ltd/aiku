<?php

/*
 * Author: stewicca <stewicalf@gmail.com> <nickel@gemini.com>
 * Copyright (c) 2026, Nickel
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Masters\MasterProductCategory\WithMasterProductCategoryCustomerTotals;
use App\Actions\OrgAction;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterProductCategoryTimeSeriesRecord;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;

class IndexMasterProductCategoryTimeSeries extends OrgAction
{
    use WithMasterProductCategoryCustomerTotals;

    public function handle(MasterProductCategory $masterProductCategory, string|null $prefix): LengthAwarePaginator
    {
        $frequency = request()->input('frequency', TimeSeriesFrequencyEnum::MONTHLY->value);
        $frequencyEnum = TimeSeriesFrequencyEnum::tryFrom($frequency) ?? TimeSeriesFrequencyEnum::MONTHLY;

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $timeSeries = $masterProductCategory->timeSeries()
            ->where('frequency', $frequencyEnum)
            ->first();

        if (!$timeSeries) {
            return new LengthAwarePaginator([], 0, 20);
        }

        $records = QueryBuilder::for(MasterProductCategoryTimeSeriesRecord::class)
            ->where('master_product_category_time_series_id', $timeSeries->id)
            ->select([
                'id',
                'from',
                'to',
                'sales_external',
                'sales_org_currency_external',
                'sales_grp_currency_external',
                'invoices',
                'refunds',
                'orders',
                'customers_invoiced',
            ])
            ->selectRaw('? as currency_code', [$masterProductCategory->group->currency->code])
            ->selectRaw('? as master_product_category_id', [$masterProductCategory->id])
            ->selectSub(
                MasterProductCategoryTimeSeriesRecord::query()
                    ->from('master_product_category_time_series_records as last_year_records')
                    ->select('last_year_records.sales_grp_currency_external')
                    ->whereColumn('last_year_records.master_product_category_time_series_id', 'master_product_category_time_series_records.master_product_category_time_series_id')
                    ->whereRaw('master_product_category_time_series_records."from" - interval \'1 year\' between last_year_records."from" and last_year_records."to"')
                    ->limit(1),
                'sales_grp_currency_external_ly'
            )
            ->defaultSort('-from')
            ->allowedSorts(['from', 'to', 'sales_external', 'sales_grp_currency_external', 'invoices', 'refunds', 'customers_invoiced'])
            ->allowedFilters([])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();

        $this->attachTotalCustomers($masterProductCategory, $records);

        return $records;
    }

    protected function attachTotalCustomers(MasterProductCategory $masterProductCategory, LengthAwarePaginator $records): void
    {
        if ($records->isEmpty()) {
            return;
        }

        $firstPurchases = $this->getCustomerFirstPurchases($masterProductCategory);

        foreach ($records as $record) {
            $record->total_customers = $this->getTotalCustomersUpTo($firstPurchases, $record->to?->toDateString());
        }
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
                        'title' => __('No sales data'),
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
                ->column('total_customers', __('Total customers'), canBeHidden: false, sortable: false, type: 'number')
                ->defaultSort('-from');
        };
    }
}
