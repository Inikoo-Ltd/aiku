<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 May 2023 22:40:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Warehouse\Hydrators;

use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Models\Inventory\Warehouse;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class WarehouseHydrateLocations implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(Warehouse $warehouse): string
    {
        return $warehouse->id;
    }


    public function handle(Warehouse $warehouse): void
    {
        $locations = $warehouse->locations()->with('stats')->get();

        $numberLocations                  = DB::connection('aiku_no_sticky')->table('locations')->whereNull('deleted_at')
            ->where('warehouse_id', $warehouse->id)->count();
        $numberOperationalLocations       = $locations->where('status', LocationStatusEnum::OPERATIONAL)->count();
        $numberEmptyLocations             = $locations->where('is_empty', true)->count();
        $numberNoStockSlotsLocations      = $locations->where('has_stock_slots', false)->count();
        $numberAllowStocksLocations       = $locations->where('allow_stocks', true)->count();
        $numberAllowFulfilmentLocations   = $locations->where('allow_fulfilment', true)->count();
        $numberAllowDropshippingLocations = $locations->where('allow_dropshipping', true)->count();


        $warehouse->stats()->update(
            [
                'number_locations'                           => $numberLocations,
                'number_empty_locations'                     => $numberEmptyLocations,
                'number_locations_status_operational'        => $numberOperationalLocations,
                'number_locations_status_broken'             => $numberLocations - $numberOperationalLocations,
                'number_locations_no_stock_slots'            => $numberNoStockSlotsLocations,
                'number_locations_stock_slots_all_empty'     => DB::connection('aiku_no_sticky')->table('locations')
                    ->whereNull('deleted_at')
                    ->where('status', LocationStatusEnum::OPERATIONAL->value)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('is_empty', true)->count(),
                'number_locations_stock_slots_partial_empty' => DB::connection('aiku_no_sticky')->table('locations')
                    ->whereNull('deleted_at')
                    ->where('status', LocationStatusEnum::OPERATIONAL->value)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('is_partially_empty', true)->count(),
                'number_locations_allow_stocks'              => $numberAllowStocksLocations,
                'number_locations_allow_fulfilment'          => $numberAllowFulfilmentLocations,
                'number_locations_allow_dropshipping'        => $numberAllowDropshippingLocations
            ]
        );
    }
}
