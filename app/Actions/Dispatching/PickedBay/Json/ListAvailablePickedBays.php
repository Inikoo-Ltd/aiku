<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 14:45:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PickedBay\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Enums\UI\Inventory\PickedBaysTabsEnum;
use App\Http\Resources\Dispatching\PickedBaysResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\PickedBay;
use App\Models\Inventory\Warehouse;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class ListAvailablePickedBays extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    public function handle(Warehouse $warehouse, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('picked_bays.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(PickedBay::class)
            ->where('picked_bays.warehouse_id', $warehouse->id);
        return $query
            ->select([
                'picked_bays.id',
                'picked_bays.code',
                'picked_bays.slug',
            ])
            ->defaultSort('picked_bays.code')
            ->allowedSorts(['code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $pickedBays): AnonymousResourceCollection
    {
        return PickedBaysResource::collection($pickedBays);
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PickedBaysTabsEnum::values());

        return $this->handle($warehouse, PickedBaysTabsEnum::BAYS->value);
    }
}
