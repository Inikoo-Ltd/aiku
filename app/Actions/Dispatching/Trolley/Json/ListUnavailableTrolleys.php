<?php

/*
 * Author: Vika Aqordi
 * Created on 27-02-2026-11h-07m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Dispatching\Trolley\Json;

use App\Actions\OrgAction;
use App\Enums\UI\Dispatch\TrolleysTabsEnum;
use App\Http\Resources\Dispatching\TrolleysResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\Trolley;
use App\Models\Inventory\Warehouse;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class ListUnavailableTrolleys extends OrgAction
{
    public function handle(Warehouse $warehouse, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('trolleys.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Trolley::class)
            ->where('trolleys.warehouse_id', $warehouse->id)
            ->whereNotNull('trolleys.current_delivery_note_id');

        return $query
            ->select([
                'trolleys.id',
                'trolleys.name',
                'trolleys.slug',
                'trolleys.current_delivery_note_id',
            ])
            ->defaultSort('trolleys.name')
            ->allowedSorts(['name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $trolleys): AnonymousResourceCollection
    {
        return TrolleysResource::collection($trolleys);
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(TrolleysTabsEnum::values());

        return $this->handle($warehouse, TrolleysTabsEnum::TROLLEYS->value);
    }
}
