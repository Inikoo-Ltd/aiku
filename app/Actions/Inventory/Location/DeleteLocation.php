<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location;

use App\Actions\Inventory\Location\Search\LocationRecordSearch;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateLocations;
use App\Actions\Inventory\WarehouseArea\Hydrators\WarehouseAreaHydrateLocations;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateLocations;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateLocations;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseSupervisorAuthorisation;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;
use App\Models\Inventory\Location;

class DeleteLocation extends OrgAction
{
    use AsController;
    use WithAttributes;
    use WithWarehouseSupervisorAuthorisation;


    private Warehouse|WarehouseArea $parent;

    public function handle(Location $location): Location
    {
        $location->delete();

        GroupHydrateLocations::dispatch($location->group)->delay($this->hydratorsDelay);
        OrganisationHydrateLocations::dispatch($location->organisation)->delay($this->hydratorsDelay);
        WarehouseHydrateLocations::dispatch($location->warehouse)->delay($this->hydratorsDelay);

        if ($location->warehouse_area_id) {
            WarehouseAreaHydrateLocations::dispatch($location->warehouseArea)->delay($this->hydratorsDelay);
        }

        LocationRecordSearch::dispatch($location);

        return $location;
    }


    public function action(Location $location): Location
    {
        $this->asAction = true;

        return $this->handle($location);
    }

    public function asController(Location $location, ActionRequest $request): Location
    {
        $this->initialisationFromWarehouse($location->warehouse, $request);

        return $this->handle($location);
    }


    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route(
            route: 'grp.org.warehouses.show.infrastructure.locations.index',
            parameters: [
                'organisation' => $this->parent->organisation->slug,
                'warehouse'    => $this->parent->slug
            ]
        );
    }

}
