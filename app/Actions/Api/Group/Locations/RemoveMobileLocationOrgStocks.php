<?php

/*
 * Author Andi Ferdiawan
 * Created on 12-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Locations;

use App\Actions\Inventory\Location\RemoveOrgStocksFromLocation;
use App\Models\Inventory\Location;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class RemoveMobileLocationOrgStocks
{
    use AsController;
    use WithMobileLocationsAccess;

    /**
     * The web remove of the Location page (RemoveOrgStocksFromLocation), with its own rules
     * (org_stock_ids) and its location edit check; stock left is written off as on the web.
     *
     * @throws \Throwable
     */
    public function asController(Location $location, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewLocations($user, $location->warehouse);

        $removed = RemoveOrgStocksFromLocation::make()->asController($location, $request);

        return response()->json([
            'removed' => $removed,
            'data'    => (new ShowMobileLocation())->forUser($location->refresh(), $user),
        ]);
    }
}
