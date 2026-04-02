<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Mar 2026 20:38:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockHistory\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrganisationStockHistory;
use App\Models\Inventory\OrgStockHistory;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexOrgStockHistories extends OrgAction
{
    public function handle(OrganisationStockHistory $organisationStockHistory, $prefix = null, ?string $filter = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereAnyWordStartWith('org_stocks.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(OrgStockHistory::class);

        $queryBuilder->leftJoin('org_stocks', 'org_stock_histories.org_stock_id', '=', 'org_stocks.id');
        $queryBuilder->where('org_stock_histories.organisation_stock_history_id', $organisationStockHistory->id);

        $queryBuilder->when($filter === 'out_of_stock', fn ($q) => $q->where('org_stock_histories.quantity_in_locations', 0));
        $queryBuilder->when($filter === 'not_sold_1y', fn ($q) => $q->where('org_stock_histories.sold_within_1y', false));
        $queryBuilder->when($filter === 'dormant_stock_1y', fn ($q) => $q->where('org_stock_histories.non_moving_1y', '>', 0));

        return $queryBuilder
            ->defaultSort('org_stocks.code')
            ->select([
                'org_stocks.id',
                'org_stocks.code',
                'org_stocks.name',
                'org_stocks.slug',
                'org_stocks.state',
                'org_stock_histories.quantity_in_locations',
                'org_stock_histories.org_stock_value',
                'org_stock_histories.grp_stock_value',
                'org_stock_histories.sold_within_1y',
                'org_stock_histories.last_sold_date',
                'org_stock_histories.non_moving_1y',
                DB::raw("'".$organisationStockHistory->organisation->currency->code."' as currency_code"),
            ])
            ->allowedSorts([
                AllowedSort::field('code', 'org_stocks.code'),
                AllowedSort::field('name', 'org_stocks.name'),
                AllowedSort::field('quantity_in_locations', 'org_stock_histories.quantity_in_locations'),
                AllowedSort::field('org_stock_value', 'org_stock_histories.org_stock_value'),
                AllowedSort::field('grp_stock_value', 'org_stock_histories.grp_stock_value'),
                AllowedSort::field('sold_within_1y', 'org_stock_histories.sold_within_1y'),
                AllowedSort::field('non_moving_1y', 'org_stock_histories.non_moving_1y'),
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(OrganisationStockHistory $organisationStockHistory, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $organisationStockHistory) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withTitle(title: __('Stock History'))
                ->withLabelRecord([__('stock'), __('stocks')])
                ->withGlobalSearch()
                ->column(key: 'code', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'quantity_in_locations', label: __('Stock'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'org_stock_value', label: __('Stock Value'), canBeHidden: false, sortable: true, type: 'currency')
                ->column(key: 'sold_within_1y', label: '', icon: 'fal fa-cash-register', tooltip: __('Sold Within 1Y'), canBeHidden: false, sortable: true, searchable: true, type: 'icon')
                ->column(key: 'non_moving_1y', label: '', icon: 'fal fa-skull-cow', tooltip: __('Non Moving 1Y'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->defaultSort('code');
        };
    }
}
