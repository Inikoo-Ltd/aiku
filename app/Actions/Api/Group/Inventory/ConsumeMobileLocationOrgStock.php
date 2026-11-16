<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-09h-42m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inventory;

use App\Actions\Inventory\LocationOrgStock\ConsumeLocationOrgStock;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ConsumeMobileLocationOrgStock
{
    use AsController;
    use WithMobileOrgStocks;

    public function asController(LocationOrgStock $locationOrgStock, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        [$warehouse, $orgStock] = $this->ensureCanChangeLink($user, $locationOrgStock);

        /** @var OrgStockMovement $movement */
        $movement = ConsumeLocationOrgStock::make()->action($locationOrgStock, Arr::only($request->all(), ['quantity', 'note']), $user);

        return $this->respondWithOrgStock($warehouse, $orgStock, $user, 'done', __('Consumed :quantity from :location.', [
            'quantity' => trimDecimalZeros(abs((float) $movement->quantity)),
            'location' => $locationOrgStock->location->code,
        ]));
    }
}
