<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 14:45:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Trolley\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
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

class ListAvailableTrolleys extends OrgAction
{
    use WithWarehouseEditAuthorisation;

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
            ->whereNull('trolleys.current_delivery_note_id');

        return $query
            ->select([
                'trolleys.id',
                'trolleys.name',
                'trolleys.slug',
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
