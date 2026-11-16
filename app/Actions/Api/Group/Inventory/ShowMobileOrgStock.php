<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-09h-42m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inventory;

use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowMobileOrgStock
{
    use AsController;
    use WithMobileOrgStocks;

    public function asController(Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewInventory($user, $warehouse);
        $this->ensureOrgStockOfWarehouse($orgStock, $warehouse);

        return response()->json(['data' => $this->orgStockDetail($warehouse, $orgStock, $user)]);
    }
}
