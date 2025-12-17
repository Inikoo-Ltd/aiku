<?php

/*
 * author Louis Perez
 * created on 25-11-2025-09h-16m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterAsset;
use App\Models\Goods\TradeUnit;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMasterProductsInTradeUnit extends OrgAction
{
    public function handle(TradeUnit $tradeUnit, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('master_assets.name', $value)
                    ->orWhereStartWith('master_assets.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterAsset::class);
        $queryBuilder->leftjoin('model_has_trade_units', function ($join) use ($tradeUnit) {
            $join->on('master_assets.id', '=', 'model_has_trade_units.model_id')
                ->where('model_has_trade_units.model_type', class_basename(MasterAsset::class));
        });
        $queryBuilder->leftJoin('master_asset_sales_intervals', 'master_assets.id', 'master_asset_sales_intervals.master_asset_id');
        $queryBuilder->leftJoin('master_shops', 'master_assets.master_shop_id', '=', 'master_shops.id');
        $queryBuilder->where('model_has_trade_units.trade_unit_id', $tradeUnit->id);

        $queryBuilder
            ->defaultSort('master_assets.code')
            ->select([
                'sales_grp_currency_all as sales_all',
                'master_assets.id',
                'master_assets.code',
                'master_shops.slug as master_shop_slug',
                'master_shops.code as master_shop_code',
                'master_shops.name as master_shop_name',
                'master_assets.name',
                'master_assets.status',
                'master_assets.price',
                'master_assets.created_at',
                'master_assets.updated_at',
                'master_assets.slug',
                'master_assets.web_images',
            ]);

        return $queryBuilder->allowedSorts(['code', 'name', 'sales_all'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null, string $bucket = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                    ]
                );

            $table->column(key: 'status', label: __('State'), canBeHidden: false, sortable: false, searchable: false);
            $table->column(key: 'master_shop_code', label: __('Master Shop'), canBeHidden: false, sortable: false, searchable: false)
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            // ->column(key: 'sales_all', label: __('Total Sales'), canBeHidden: false, sortable: true, searchable: false);
        };
    }

}
