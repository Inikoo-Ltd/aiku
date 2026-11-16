<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-09h-42m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inventory;

use App\Actions\Inventory\LocationOrgStock\DeleteLocationOrgStock;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class RemoveMobileOrgStockLocations
{
    use AsController;
    use WithMobileOrgStocks;

    /**
     * @throws \Throwable
     */
    public function asController(Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanChangeOrgStock($user, $warehouse, $orgStock);

        $data = Validator::make($request->all(), [
            'location_org_stock_ids'   => ['required', 'array', 'min:1'],
            'location_org_stock_ids.*' => ['required', 'integer', 'min:1', 'max:'.self::MAX_ID, 'distinct'],
        ])->validate();

        $links      = $this->ownLinks($orgStock, $warehouse, $data['location_org_stock_ids']);
        $writtenOff = $links->sum(fn (LocationOrgStock $link) => (float) $link->quantity);

        DB::transaction(function () use ($links, $user) {
            foreach ($links as $link) {
                DeleteLocationOrgStock::make()->action($link, $user);
            }
        });

        $locations = $links->map(fn (LocationOrgStock $link) => $link->location->code)->sort()->implode(', ');

        return $this->respondWithOrgStock($warehouse, $orgStock, $user, 'done', abs($writtenOff) > 0.000001
            ? __('Removed from :locations. The :quantity left there was written off.', ['locations' => $locations, 'quantity' => trimDecimalZeros($writtenOff)])
            : __('Removed from :locations.', ['locations' => $locations]));
    }
}
