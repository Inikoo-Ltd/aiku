<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 May 2023 22:54:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location\Hydrators;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateLocations;
use App\Models\Inventory\Location;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class LocationHydrateOrgStocks implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(Location $location): string
    {
        return $location->id;
    }

    public function handle(Location $location, $delay = 2): void
    {
        $slots             = $location->locationOrgStocks()->where('dropshipping_pipe', false)->count();
        $dropShippingSlots = $location->locationOrgStocks()->where('dropshipping_pipe', true)->count();


        $location->update(
            [
                'has_stock_slots'        => $slots > 0,
                'has_dropshipping_slots' => $dropShippingSlots > 0
            ]
        );


        $numberSlotsWithNoStock = $location->locationOrgStocks()->where('quantity', 0)->count();

        $totalSlots = $slots + $dropShippingSlots;


        if ($totalSlots == 0) {
            $isEmpty          = true;
            $isPartiallyEmpty = false;
        } else {
            $isEmpty          = ($totalSlots - $numberSlotsWithNoStock) == 0;
            $isPartiallyEmpty = ($totalSlots - $numberSlotsWithNoStock) > 0 && $numberSlotsWithNoStock > 0;
        }

        $location->update([
            'is_empty'           => $isEmpty,
            'is_partially_empty' => $isPartiallyEmpty,
        ]);


        $location->stats()->update([
            'number_org_stock_slots'   => $totalSlots,
            'number_empty_stock_slots' => $numberSlotsWithNoStock,
        ]);

        WarehouseHydrateLocations::dispatch($location->warehouse)->delay($delay);
    }

}
