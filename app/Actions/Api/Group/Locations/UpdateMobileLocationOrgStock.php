<?php

/*
 * Author Andi Ferdiawan
 * Created on 12-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Locations;

use App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UpdateMobileLocationOrgStock
{
    use AsController;
    use WithMobileLocationsAccess;

    /**
     * The SKO-in-location settings of the web stock card (UpdateLocationOrgStock): type, the
     * wholesale and dropshipping picking defaults, min, max and replenishment levels and notes.
     * The web action skips its own authorisation when called from a request, so the stock edit
     * rights of its WithLocationOrgStockActionAuthorisation are checked here.
     */
    public function asController(LocationOrgStock $locationOrgStock, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user     = $request->user();
        $location = $locationOrgStock->location;
        $this->ensureCanViewLocations($user, $location->warehouse);
        abort_unless(self::canEditStocks($user, $location->warehouse), 403);

        UpdateLocationOrgStock::make()->asController($locationOrgStock, $request);

        return response()->json(['data' => (new ShowMobileLocation())->forUser($location->refresh(), $user)]);
    }
}
