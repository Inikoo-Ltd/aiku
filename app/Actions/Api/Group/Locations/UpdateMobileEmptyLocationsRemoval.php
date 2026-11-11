<?php

/*
 * Author Andi Ferdiawan
 * Created on 11-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Locations;

use App\Actions\Inventory\Warehouse\UpdateWarehouseAutoDisassociateEmptyLocations;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UpdateMobileEmptyLocationsRemoval
{
    use AsController;
    use WithMobileLocationsAccess;

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewLocations($user, $warehouse);
        abort_unless(self::canEditStocks($user, $warehouse), 403);

        $data = Validator::make($request->all(), [
            'enabled' => ['required', 'boolean'],
        ])->validate();

        $warehouse = UpdateWarehouseAutoDisassociateEmptyLocations::make()->action($warehouse, [
            'auto_disassociate_empty_locations' => (bool) $data['enabled'],
        ]);

        return response()->json(['data' => GetMobileEmptyLocationsRemoval::run($warehouse->refresh(), true)]);
    }
}
