<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-09h-42m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inventory;

use App\Actions\Inventory\LocationOrgStock\MoveOrgStockToMultipleLocations;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class MoveMobileLocationOrgStock
{
    use AsController;
    use WithMobileOrgStocks;

    /**
     * @throws \Throwable
     */
    public function asController(LocationOrgStock $locationOrgStock, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        [$warehouse, $orgStock] = $this->ensureCanChangeLink($user, $locationOrgStock);

        $data = Validator::make($request->all(), [
            'targets'                         => ['required', 'array', 'min:1'],
            'targets.*'                       => ['required', 'array'],
            'targets.*.location_org_stock_id' => ['required', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'targets.*.quantity'              => ['required', 'numeric', 'gt:0'],
        ])->validate();

        $targets = array_map(fn (array $target) => Arr::only($target, ['location_org_stock_id', 'quantity']), $data['targets']);
        $this->ownLinks($orgStock, $warehouse, array_unique(Arr::pluck($targets, 'location_org_stock_id')));

        MoveOrgStockToMultipleLocations::make()->action($locationOrgStock, ['targets' => $targets], $user);

        return $this->respondWithOrgStock($warehouse, $orgStock, $user, 'done', __('Moved :quantity from :location.', [
            'quantity' => trimDecimalZeros(collect($targets)->sum(fn (array $target) => (float) $target['quantity'])),
            'location' => $locationOrgStock->location->code,
        ]));
    }
}
