<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Dec 2025 23:18:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrgStocksInTradeUnit extends OrgAction
{
    public function handle(TradeUnit $tradeUnit, $prefix = null): LengthAwarePaginator
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

        $queryBuilder = QueryBuilder::for(OrgStock::class);
        $queryBuilder->leftjoin('model_has_trade_units', function ($join) {
            $join->on('org_stocks.id', '=', 'model_has_trade_units.model_id')
                ->where('model_has_trade_units.model_type', 'OrgStock');
        });
        $queryBuilder->where('model_has_trade_units.trade_unit_id', $tradeUnit->id);


        $queryBuilder
            ->defaultSort('org_stocks.code')
            ->select([
                'org_stocks.id',
                'org_stocks.code',
                'org_stocks.slug',
                'org_stocks.name',
                'org_stocks.state',
                'model_has_trade_units.quantity as quantity',
            ]);


        return $queryBuilder->allowedSorts(['code', 'name', 'quantity'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->defaultSort('code')
                ->withGlobalSearch()
                ->dateInterval($this->dateInterval)
                ->withModelOperations($modelOperations)
                ->withEmptyState([])
                ->column(key: 'state', label: '', icon: 'fal fa-yin-yang', canBeHidden: false, sortable: true, type: 'avatar')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity', label: __('Quantity'), canBeHidden: false, sortable: true, searchable: true);
        };
    }
}
