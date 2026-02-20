<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:37:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\GrpAction;
use App\Enums\Goods\TradeUnit\TradeUnitStatusEnum;
use App\Http\Resources\Goods\TradeUnitsForMasterResource;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetAllTradeUnitsInProduct extends GrpAction
{
    public function asController(Product $product, ActionRequest $request): LengthAwarePaginator
    {
        $parent = $product;
        $this->initialisation($parent->group, $request);

        return $this->handle($product);
    }

    public function handle(Product $product, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('trade_units.code', $value)
                    ->orWhereStartWith('trade_units.name', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(TradeUnit::class);
        $queryBuilder->where('trade_units.group_id', $product->group_id);
        $queryBuilder->where('status', TradeUnitStatusEnum::ACTIVE);

        return $queryBuilder
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
                'trade_units.marketing_dimensions',
                'trade_units.marketing_weight',
            ])
            ->addSelect([
                'quantity' => DB::table('model_has_trade_units')
                    ->select('quantity')
                    ->whereColumn('model_has_trade_units.trade_unit_id', 'trade_units.id')
                    ->where('model_has_trade_units.model_type', 'Stock')
                    ->limit(1)
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
