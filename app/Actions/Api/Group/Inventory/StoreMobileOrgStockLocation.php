<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-09h-42m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inventory;

use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock;
use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class StoreMobileOrgStockLocation
{
    use AsController;
    use WithMobileOrgStocks;

    /**
     * @return array<int, string>
     *
     * @throws \Throwable
     */
    public function handle(OrgStock $orgStock, Location $location, LocationStockTypeEnum $type, User $user): array
    {
        $demoted = $type === LocationStockTypeEnum::PICKING
            ? LocationOrgStock::where('org_stock_id', $orgStock->id)->where('type', LocationStockTypeEnum::PICKING->value)->with('location')->get()
            : collect();

        DB::transaction(function () use ($orgStock, $location, $type, $user) {
            $locationOrgStock = StoreLocationOrgStock::make()->action($orgStock, $location, ['type' => LocationStockTypeEnum::STORING->value], user: $user);

            if ($type === LocationStockTypeEnum::PICKING) {
                UpdateLocationOrgStock::make()->action($locationOrgStock, ['type' => LocationStockTypeEnum::PICKING->value]);
            }
        });

        return $demoted->map(fn (LocationOrgStock $link) => $link->location?->code)->filter()->sort()->values()->all();
    }

    /**
     * @throws ValidationException
     */
    private function ensureCanHoldOrgStock(Location $location, OrgStock $orgStock): void
    {
        $refusal = match (true) {
            $location->status !== LocationStatusEnum::OPERATIONAL => __('This location is not operational.'),
            !$location->allow_stocks                              => __('This location does not take SKOs.'),
            LocationOrgStock::where('location_id', $location->id)->where('org_stock_id', $orgStock->id)->exists() => __('This SKO is already in that location'),
            default                                               => null,
        };

        if ($refusal) {
            throw ValidationException::withMessages(['location_id' => $refusal]);
        }
    }

    /**
     * @throws \Throwable
     */
    public function asController(Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanChangeOrgStock($user, $warehouse, $orgStock);

        $data = Validator::make($request->all(), [
            'location_id' => ['required', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'type'        => ['required', Rule::enum(LocationStockTypeEnum::class)],
        ])->validate();

        $location = Location::where('warehouse_id', $warehouse->id)->find($data['location_id']);
        abort_unless($location, 404);
        $this->ensureCanHoldOrgStock($location, $orgStock);

        $type    = LocationStockTypeEnum::from($data['type']);
        $demoted = $this->handle($orgStock, $location, $type, $user);

        $message = match (true) {
            $demoted !== []                          => __('Added to :location as the picking location; :previous is now storing.', ['location' => $location->code, 'previous' => implode(', ', $demoted)]),
            $type === LocationStockTypeEnum::PICKING => __('Added to :location as the picking location.', ['location' => $location->code]),
            default                                  => __('Added to :location as a storing location.', ['location' => $location->code]),
        };

        return $this->respondWithOrgStock($warehouse, $orgStock, $user, 'done', $message);
    }
}
