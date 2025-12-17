<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 17 Dec 2025 00:57:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\UI;

use App\Actions\OrgAction;
use App\Actions\Goods\TradeUnit\UI\Traits\WithTradeUnitIndex;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexTradeUnitsInOrgStock extends OrgAction
{
    use WithTradeUnitIndex;

    public function handle(OrgStock $orgStock, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = $this->tradeUnitGlobalSearch();

        $this->updateQueryBuilderParametersIfPrefixed($prefix);

        $queryBuilder = $this->baseTradeUnitIndexBuilder();
        $queryBuilder->leftjoin('model_has_trade_units', 'trade_units.id', '=', 'model_has_trade_units.trade_unit_id')
            ->where('model_has_trade_units.model_type', 'OrgStock')
            ->where('model_has_trade_units.model_id', $orgStock->id);


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
                'model_has_trade_units.quantity as quantity'
            ]);
        return $this->finalizeTradeUnitIndex(
            queryBuilder: $queryBuilder,
            allowedSorts: ['code', 'type', 'name'],
            globalSearch: $globalSearch,
            prefix: $prefix
        );
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            $this->setupTradeUnitTable(
                table: $table,
                modelOperations: $modelOperations,
                prefix: $prefix,
                withLabelRecord: true,
                emptyState: [
                    'title' => __("No trade units found"),
                ]
            );

            $this->addColumnCodeAndName($table);
            $this->addColumnNetWeight($table, 'Weight');
            $this->addColumnType($table, 'Type');
            $this->addColumnQuantity($table, 'Quantity', false, false, 'right');
        };
    }
}
