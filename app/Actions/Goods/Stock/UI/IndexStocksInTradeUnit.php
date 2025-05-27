<?php
/*
 * author Arya Permana - Kirin
 * created on 27-05-2025-14h-14m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Goods\Stock\UI;

use App\Actions\Goods\StockFamily\UI\ShowStockFamily;
use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Http\Resources\Goods\StocksResource;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexStocksInTradeUnit extends OrgAction
{
    public function handle(TradeUnit $tradeUnit, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('stocks.code', $value)
                    ->orWhereAnyWordStartWith('stocks.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Stock::class);
        $queryBuilder->leftjoin('model_has_trade_units', function ($join) use ($tradeUnit) {
            $join->on('stocks.id', '=', 'model_has_trade_units.model_id')
                ->where('model_has_trade_units.model_type', class_basename(Stock::class));
        });
        $queryBuilder->where('model_has_trade_units.trade_unit_id', $tradeUnit->id);


        $queryBuilder
            ->defaultSort('stocks.code')
            ->select([
                'stocks.code',
                'stocks.slug',
                'stocks.name',
                'stocks.unit_value',
                'stocks.state',

            ]);

        return $queryBuilder->allowedSorts(['code', 'name', 'unit_value'])
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
                ->column(key: 'state', label: __('state'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'unit_value', label: __('unit value'), canBeHidden: false, sortable: true, searchable: true);
        };
    }
}
