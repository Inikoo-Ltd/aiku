<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Mon, 30 Dec 2025 20:00:00 Western Indonesia Time, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Goods\Asset\UI;

use App\Actions\OrgAction;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\AssetTimeSeriesRecord;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;

class IndexAssetTimeSeries extends OrgAction
{
    public function handle(Asset $asset, string|null $prefix): LengthAwarePaginator
    {
        $frequency = request()->get('frequency', TimeSeriesFrequencyEnum::DAILY->value);
        $frequencyEnum = TimeSeriesFrequencyEnum::tryFrom($frequency) ?? TimeSeriesFrequencyEnum::DAILY;

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $timeSeries = $asset->timeSeries()
            ->where('frequency', $frequencyEnum)
            ->first();

        if (!$timeSeries) {
            return new LengthAwarePaginator([], 0, 20);
        }

        return QueryBuilder::for(AssetTimeSeriesRecord::class)
            ->where('asset_time_series_id', $timeSeries->id)
            ->select([
                'id',
                'from',
                'to',
                'sales',
                'sales_org_currency',
                'sales_grp_currency',
                'invoices',
                'refunds',
                'orders',
                'delivery_notes',
                'customers_invoiced',
            ])
            ->defaultSort('-from')
            ->allowedSorts(['from', 'to', 'sales', 'invoices', 'refunds', 'customers_invoiced'])
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
                        'title' => __('No sales data'),
                        'description' => __('No sales records found for this period'),
                    ]
                )
                ->withFrequency()
                ->column('period', __('Period'), canBeHidden: false, sortable: false)
                ->column('sales', __('Sales'), canBeHidden: false, sortable: true, type: 'number')
                ->column('invoices', __('Invoices'), canBeHidden: false, sortable: true, type: 'number')
                ->column('refunds', __('Refunds'), canBeHidden: false, sortable: true, type: 'number')
                ->column('customers_invoiced', __('Customers'), canBeHidden: false, sortable: true, type: 'number')
                ->defaultSort('-from');
        };
    }
}
