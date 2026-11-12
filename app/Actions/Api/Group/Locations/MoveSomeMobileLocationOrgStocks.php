<?php

/*
 * Author Andi Ferdiawan
 * Created on 12-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Locations;

use App\Actions\Inventory\Location\PartialMoveLocationOrgStocks;
use App\Models\Inventory\Location;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class MoveSomeMobileLocationOrgStocks
{
    use AsController;
    use WithMobileLocationsAccess;

    /**
     * The web partial move of the Location page (PartialMoveLocationOrgStocks), with its own
     * rules (location_id, org_stocks with quantity and remove_after_move, reason, note) and its
     * location edit check.
     *
     * @throws \Throwable
     */
    public function asController(Location $location, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewLocations($user, $location->warehouse);

        PartialMoveLocationOrgStocks::make()->asController($location, $request);

        return response()->json(['data' => (new ShowMobileLocation())->forUser($location->refresh(), $user)]);
    }
}
