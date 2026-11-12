<?php

/*
 * Author Andi Ferdiawan
 * Created on 11-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Locations;

use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileLocationsOverview
{
    use AsController;
    use WithMobileLocationsAccess;

    /**
     * The same warehouse stats the web warehouse page shows (GetWarehouseShowcase), plus the
     * empty location removal panel of its Warehouse tab.
     *
     * @return array<string, mixed>
     */
    public function handle(Warehouse $warehouse, bool $canEditStocks = false): array
    {
        $stats = $warehouse->stats;

        return [
            'warehouse'                        => [
                'id'   => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
            ],
            'number_areas'                     => (int) $stats?->number_warehouse_areas,
            'number_locations'                 => (int) $stats?->number_locations,
            'number_locations_operational'     => (int) $stats?->number_locations_status_operational,
            'number_locations_broken'          => (int) $stats?->number_locations_status_broken,
            'number_locations_empty'           => (int) $stats?->number_locations_stock_slots_all_empty,
            'number_locations_partially_empty' => (int) $stats?->number_locations_stock_slots_partial_empty,
            'stock_value'                      => (float) $stats?->stock_value,
            'empty_locations_removal'          => GetMobileEmptyLocationsRemoval::run($warehouse, $canEditStocks),
        ];
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewLocations($user, $warehouse);

        return response()->json(['data' => $this->handle($warehouse, self::canEditStocks($user, $warehouse))]);
    }
}
