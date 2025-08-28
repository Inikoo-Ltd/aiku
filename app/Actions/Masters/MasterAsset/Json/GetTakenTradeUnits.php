<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:37:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Json;

use App\Actions\GrpAction;
use App\Http\Resources\Goods\TradeUnitsForMasterResource;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterProductCategory;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetTakenTradeUnits extends GrpAction
{
    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($masterProductCategory->group, $request);

        return $this->handle(parent: $masterProductCategory);
    }

    public function handle(MasterProductCategory $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('trade_units.code', $value)
                    ->orWhereStartWith('trade_units.name', $value);
            });
        });

        $masterAssetIds = $parent->masterAssets()->pluck('id')->toArray();

        $queryBuilder = QueryBuilder::for(TradeUnit::class);
        $queryBuilder->where('trade_units.group_id', $parent->group_id)
            ->where('trade_units.code', 'like', $parent->code . '%')
            ->leftJoin('model_has_trade_units', function ($join) {
                $join->on('trade_units.id', '=', 'model_has_trade_units.trade_unit_id')
                    ->where('model_has_trade_units.model_type', '=', 'MasterAsset');
            });
        $queryBuilder->whereIn('model_has_trade_units.model_id', $masterAssetIds);


        return $queryBuilder
            ->groupBy('trade_units.id')
            ->defaultSort('trade_units.code')
            ->select([
                'trade_units.code',
                'trade_units.slug',
                'trade_units.name',
                'trade_units.type',
                'trade_units.description',
                'trade_units.gross_weight',
                'trade_units.net_weight',
                'trade_units.marketing_dimensions',
                'trade_units.volume',
                'trade_units.type',
                'trade_units.image_id',
                'trade_units.id',
                'trade_units.cost_price',
            ])
            ->allowedSorts(['code', 'name', 'net_weight', 'gross_weight'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $tradeUnits): AnonymousResourceCollection
    {
        return TradeUnitsForMasterResource::collection($tradeUnits);
    }
}
