<?php

/*
 * author Arya Permana - Kirin
 * created on 25-03-2025-14h-45m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\Pallet\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseManagementAuthorisation;
use App\Http\Resources\Fulfilment\MayaPalletsResource;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class IndexPalletsInLocation extends OrgAction
{
    use WithWarehouseManagementAuthorisation;

    public function handle(Location $parent, $prefix = null): LengthAwarePaginator
    {
        return IndexPallets::run($parent.$prefix);
    }

    public function tableStructure(Location $parent, $prefix = null, $modelOperations = []): Closure
    {
        return IndexPallets::make()->tableStructure($parent, $prefix, $modelOperations);
    }


    public function jsonResponse(LengthAwarePaginator $pallets, ActionRequest $request): AnonymousResourceCollection
    {
        if ($request->hasHeader('Maya-Version')) {
            return MayaPalletsResource::collection($pallets);
        }

        return PalletsResource::collection($pallets);
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, Location $location, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($location, 'pallets');
    }
}
