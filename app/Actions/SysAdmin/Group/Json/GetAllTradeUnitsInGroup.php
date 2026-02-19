<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Feb 2026 15:22:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Json;

use App\Actions\GrpAction;
use App\Enums\Goods\TradeUnit\TradeUnitStatusEnum;
use App\Http\Resources\Goods\TradeUnitsForMasterResource;
use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetAllTradeUnitsInGroup extends GrpAction
{
    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation(group(), $request);

        return $this->handle($this->group);
    }

    public function handle(Group $group, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('trade_units.code', $value)
                    ->orWhereStartWith('trade_units.name', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(TradeUnit::class);
        $queryBuilder->where('trade_units.group_id', $group->id);
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
