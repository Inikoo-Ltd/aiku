<?php

/*
 * Author: Vika Aqordi
 * Created on 05-02-2026-15h-42m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Inventory\PickingTrolley\Json;


use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Actions\UI\Dispatch\ShowDispatchHub;
use App\Enums\UI\Inventory\PickingTrolleysTabsEnum;
use App\Http\Resources\Inventory\PickingTrolleyResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\PickingTrolley;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class ListAvailablePickingTrolley extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    public function handle(Warehouse $warehouse, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('picking_trolleys.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(PickingTrolley::class)
            ->where('picking_trolleys.warehouse_id', $warehouse->id)
            ->whereNull('picking_trolleys.delivery_note_id');

        return $query
            ->select([
                'picking_trolleys.id',
                'picking_trolleys.code',
                'picking_trolleys.slug',
            ])
            ->defaultSort('picking_trolleys.code')
            ->allowedSorts(['code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $pickingTrolleys): AnonymousResourceCollection
    {
        return PickingTrolleyResource::collection($pickingTrolleys);
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PickingTrolleysTabsEnum::values());

        return $this->handle($warehouse, PickingTrolleysTabsEnum::TROLLEYS->value);
    }
}
