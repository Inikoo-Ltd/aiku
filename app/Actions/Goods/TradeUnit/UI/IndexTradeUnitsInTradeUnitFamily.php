<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\UI;

use App\Actions\GrpAction;
use App\Actions\Goods\TradeUnit\UI\Traits\WithTradeUnitIndex;
use App\Http\Resources\Goods\TradeUnitsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\TradeUnitFamily;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IndexTradeUnitsInTradeUnitFamily extends GrpAction
{
    use WithTradeUnitIndex;

    public function handle(TradeUnitFamily $tradeUnitFamily, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = $this->tradeUnitGlobalSearch();

        $this->updateQueryBuilderParametersIfPrefixed($prefix);

        $queryBuilder = $this->baseTradeUnitIndexBuilder();
        $queryBuilder->where('trade_units.trade_unit_family_id', $tradeUnitFamily->id);
        $queryBuilder->leftJoin('trade_unit_stats', 'trade_unit_stats.trade_unit_id', 'trade_units.id');



        $queryBuilder
            ->defaultSort('trade_units.code')
            ->select([
                'trade_units.code',
                'trade_units.slug',
                'trade_units.name',
                'trade_units.description',
                'trade_units.gross_weight',
                'trade_units.net_weight',
                'trade_units.marketing_dimensions',
                'trade_units.volume',
                'trade_units.type',
                'trade_units.id',
                'trade_units.status',
                'trade_unit_stats.number_current_stocks',
                'trade_unit_stats.number_current_products',
            ]);
        return $this->finalizeTradeUnitIndex(
            queryBuilder: $queryBuilder,
            allowedSorts: ['code', 'type', 'name', 'number_current_stocks','number_current_products'],
            globalSearch: $globalSearch,
            prefix: $prefix
        );
    }

    public function jsonResponse(LengthAwarePaginator $tradeUnits): AnonymousResourceCollection
    {
        return TradeUnitsResource::collection($tradeUnits);
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            $this->setupTradeUnitTable(
                table: $table,
                modelOperations: $modelOperations,
                prefix: $prefix,
                withLabelRecord: false,
                emptyState: [
                    'title' => __("No Trade Units found"),
                ]
            );

            $this->addColumnStatusAvatar($table);
            $this->addColumnCodeAndName($table);
            $this->addColumnNumberCurrentProducts($table);
            $this->addColumnNetWeight($table, 'Weight');
            $this->addColumnType($table, 'Unit label');
        };
    }

}
