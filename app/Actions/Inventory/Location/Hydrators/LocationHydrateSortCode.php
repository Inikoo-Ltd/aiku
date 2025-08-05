<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 31 Jul 2025 13:48:32 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location\Hydrators;

use App\Actions\Helpers\CreateSortCode;
use App\Models\Inventory\Location;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * LocationHydrateSortCode
 *
 * This action hydrates the sort_code field of a location using the CreateSortCode helper.
 * The sort_code is used for more effective sorting of locations in reports and displays.
 */
class LocationHydrateSortCode implements ShouldBeUnique
{
    use AsAction;


    public function getJobUniqueId(Location $location): string
    {
        return $location->id;
    }


    public function handle(Location $location): Location
    {
        $code = $location->code;

        // Generate the sort code using the CreateSortCode helper
        $sortCode = CreateSortCode::run($code);

        $warehouseArea = $location->warehouseArea;
        if ($warehouseArea) {
            $pickingPosition = $warehouseArea->picking_position;

            // Format the picking position for proper SQL ordering
            // If it's null, use a default value that sorts first
            // If it's a float, format it with 6 decimal places, padded with zeros
            if ($pickingPosition === null) {
                $formattedPosition = '000000000000';  // 12 zeros to sort first
            } else {
                // Format with 6 decimal places, ensuring leading zeros for proper sorting
                $formattedPosition = sprintf('%012.6f', $pickingPosition);
            }

            $sortCode = $formattedPosition.'-'.CreateSortCode::run($warehouseArea->code).'-'.$sortCode;
        }

        // Update the location with the new sort code

        $location->update([
            'sort_code' => $sortCode
        ]);

        return $location;
    }
}
