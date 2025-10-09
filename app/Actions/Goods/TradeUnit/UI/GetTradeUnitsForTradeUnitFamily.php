<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\UI;

use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Http\Resources\Goods\TradeUnitsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitFamily;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetTradeUnitsForTradeUnitFamily extends GrpAction
{
    use WithGoodsAuthorisation;

    private Group $parent;


    public function asController(TradeUnitFamily $tradeUnitFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisation($this->parent, $request);

        return $this->handle($tradeUnitFamily);
    }

    public function handle(TradeUnitFamily $tradeUnitFamily, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('trade_units.code', $value)
                    ->orWhereAnyWordStartWith('trade_units.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(TradeUnit::class);
        $queryBuilder->where('trade_units.group_id', $this->group->id);
        $queryBuilder->where(function ($query) use ($tradeUnitFamily) {
            $query->whereNull('trade_units.trade_unit_family_id')
                ->orWhereNot('trade_units.trade_unit_family_id', $tradeUnitFamily->id);
        });


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
                'trade_units.id'
            ]);


        return $queryBuilder->allowedSorts(['code', 'type', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $tradeUnit): AnonymousResourceCollection
    {
        return TradeUnitsResource::collection($tradeUnit);
    }
}
