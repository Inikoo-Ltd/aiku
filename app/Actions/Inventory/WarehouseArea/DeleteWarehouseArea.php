<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseArea;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateWarehouseAreas;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWarehouseAreas;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWarehouseAreas;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseSupervisorAuthorisation;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\WarehouseArea;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteWarehouseArea extends OrgAction
{
    use AsController;
    use WithAttributes;
    use WithWarehouseSupervisorAuthorisation;

    public function handle(WarehouseArea $warehouseArea): WarehouseArea
    {
        DB::transaction(function () use ($warehouseArea) {
            $warehouseArea->locations()->update(['warehouse_area_id' => null]);
            LocationOrgStock::where('warehouse_area_id', $warehouseArea->id)->update(['warehouse_area_id' => null]);
            Pallet::where('warehouse_area_id', $warehouseArea->id)->update(['warehouse_area_id' => null]);
            $warehouseArea->stats()->delete();
            $warehouseArea->delete();
        });

        WarehouseHydrateWarehouseAreas::dispatch($warehouseArea->warehouse)->delay($this->hydratorsDelay);
        GroupHydrateWarehouseAreas::dispatch($warehouseArea->group)->delay($this->hydratorsDelay);
        OrganisationHydrateWarehouseAreas::dispatch($warehouseArea->organisation)->delay($this->hydratorsDelay);

        return $warehouseArea;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            "inventory.{$this->organisation->id}.edit",
            "supervisor-locations.{$this->warehouse->id}",
            "locations.{$this->warehouse->id}.edit",
        ]);
    }

    public function asController(WarehouseArea $warehouseArea, ActionRequest $request): WarehouseArea
    {
        $this->initialisationFromWarehouse($warehouseArea->warehouse, $request);

        return $this->handle($warehouseArea);
    }


    public function htmlResponse(WarehouseArea $warehouseArea): RedirectResponse
    {
        return Redirect::route(
            route: 'grp.org.warehouses.show.infrastructure.warehouse_areas.index',
            parameters: [
                'organisation' => $warehouseArea->organisation->slug,
                'warehouse'    => $warehouseArea->warehouse->slug
            ]
        );
    }

}
