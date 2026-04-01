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
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrgStockHistories extends OrgAction
{
    public function handle(OrganisationStockHistory $organisationStockHistory, $prefix = null): LengthAwarePaginator
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
            ])
            ->allowedSorts(['code', 'name', 'quantity_in_locations', 'org_stock_value', 'grp_stock_value'])
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

            $currency = $organisationStockHistory->organisation->currency->symbol;

            $table
                ->withTitle(title: __('Stock History'))
                ->withLabelRecord([__('stock'), __('stocks')])
                ->withGlobalSearch()
                ->column(key: 'code', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'quantity_in_locations', label: __('Stock'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'org_stock_value', label: __('Value ('.$currency.')'), canBeHidden: false, sortable: true, type: 'currency')
                ->defaultSort('code');
        };
    }


}
