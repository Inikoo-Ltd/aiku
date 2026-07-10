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
use Lorisleiva\Actions\Concerns\AsAction;

class WarehouseHydrateLocations implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Warehouse $warehouse): string
    {
        return $warehouse->id;
    }


    public function handle(Warehouse $warehouse): void
    {
        $locations = $warehouse->locations()->with('stats')->get();

        $numberLocations                    = $locations->count();
        $numberOperationalLocations         = $locations->where('status', LocationStatusEnum::OPERATIONAL)->count();
        $numberEmptyLocations               = $locations->where('is_empty', true)->count();
        $numberNoStockSlotsLocations        = $locations->where('has_stock_slots', false)->count();
        $numberAllowStocksLocations         = $locations->where('allow_stocks', true)->count();
        $numberAllowFulfilmentLocations     = $locations->where('allow_fulfilment', true)->count();
        $numberAllowDropshippingLocations   = $locations->where('allow_dropshipping', true)->count();

        $numberAllEmptyStockSlots           = $locations->filter(function ($location) {
            return $location->stats && $location->stats->number_org_stock_slots > 0
                && $location->stats->number_empty_stock_slots >= $location->stats->number_org_stock_slots;
        })->count();
        $numberPartialEmptyStockSlots       = $locations->filter(function ($location) {
            return $location->stats && $location->stats->number_empty_stock_slots > 0
                && $location->stats->number_empty_stock_slots < $location->stats->number_org_stock_slots;
        })->count();

        $warehouse->stats()->update(
            [
                'number_locations'                          => $numberLocations,
                'number_empty_locations'                    => $numberEmptyLocations,
                'number_locations_status_operational'       => $numberOperationalLocations,
                'number_locations_status_broken'            => $numberLocations - $numberOperationalLocations,
                'number_locations_no_stock_slots'           => $numberNoStockSlotsLocations,
                'number_locations_stock_slots_all_empty'    => $numberAllEmptyStockSlots,
                'number_locations_stock_slots_partial_empty' => $numberPartialEmptyStockSlots,
                'number_locations_allow_stocks'             => $numberAllowStocksLocations,
                'number_locations_allow_fulfilment'         => $numberAllowFulfilmentLocations,
                'number_locations_allow_dropshipping'       => $numberAllowDropshippingLocations
            ]
        );
    }
}
