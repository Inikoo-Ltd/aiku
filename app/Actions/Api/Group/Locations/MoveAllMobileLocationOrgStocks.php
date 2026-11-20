<?php

/*
 * Author Andi Ferdiawan
 * Created on 12-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Locations;

use App\Actions\Api\Group\Inventory\WithMobileStockWriteRequests;
use App\Actions\Inventory\Location\MassMoveLocationOrgStocks;
use App\Models\Inventory\Location;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class MoveAllMobileLocationOrgStocks
{
    use AsController;
    use WithMobileLocationsAccess;
    use WithMobileStockWriteRequests;

    /**
     * The web "Move All SKO" of the Location page (MassMoveLocationOrgStocks), with its own
     * rules (location_id, remove_after_move, reason, note) and its location edit check.
     *
     * @throws \Throwable
     */
    public function asController(Location $location, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewLocations($user, $location->warehouse);

        return $this->oncePerClientRequest(
            $user,
            $this->optionalClientRequestId($request->all()),
            function () use ($location, $request, $user) {
                MassMoveLocationOrgStocks::make()->asController($location, $request);

                return response()->json(['data' => (new ShowMobileLocation())->forUser($location->refresh(), $user)]);
            },
            fn () => response()->json(['data' => (new ShowMobileLocation())->forUser($location->refresh(), $user)])
        );
    }
}
