<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location;

use App\Actions\Inventory\LocationOrgStock\DeleteLocationOrgStock;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateLocations;
use App\Actions\Inventory\WarehouseArea\Hydrators\WarehouseAreaHydrateLocations;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateLocations;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateLocations;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseSupervisorAuthorisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;
use App\Models\Inventory\Location;

class DeleteLocation extends OrgAction
{
    use AsController;
    use WithAttributes;
    use WithWarehouseSupervisorAuthorisation;


    private Location $location;

    /**
     * @throws \Throwable
     */
    public function handle(Location $location): Location
    {
        $this->location = $location;

        if (!self::isEmpty($location)) {
            throw ValidationException::withMessages([
                'location' => __('Location :code is not empty, move its contents before deleting it.', ['code' => $location->code])
            ]);
        }

        foreach ($location->locationOrgStocks()->get() as $locationOrgStock) {
            DeleteLocationOrgStock::make()->action($locationOrgStock);
        }

        $location->delete();

        GroupHydrateLocations::dispatch($location->group)->delay($this->hydratorsDelay);
        OrganisationHydrateLocations::dispatch($location->organisation)->delay($this->hydratorsDelay);
        WarehouseHydrateLocations::dispatch($location->warehouse)->delay($this->hydratorsDelay);

        if ($location->warehouse_area_id) {
            WarehouseAreaHydrateLocations::dispatch($location->warehouseArea)->delay($this->hydratorsDelay);
        }

        return $location;
    }


    public static function isEmpty(Location $location): bool
    {
        return !$location->locationOrgStocks()->where('quantity', '!=', 0)->exists()
            && !$location->pallets()->exists();
    }

    /**
     * @throws \Throwable
     */
    public function action(Location $location): Location
    {
        $this->asAction = true;

        return $this->handle($location);
    }

    /**
     * @throws \Throwable
     */
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
                'organisation' => $this->location->organisation->slug,
                'warehouse'    => $this->location->warehouse->slug
            ]
        );
    }

}
