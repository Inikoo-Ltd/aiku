<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 May 2023 22:57:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location\Hydrators;

use App\Models\Inventory\Location;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class LocationHydrateStockValue implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Location $location): string
    {
        return $location->id;
    }


    public function handle(Location $location): void
    {
        $orgStockValue          = 0;
        $orgStockCommercialValue = 0;
        foreach ($location->locationOrgStocks() as $locationOrgStock) {
            $orgStock = $locationOrgStock->orgStock;
            $orgStockValue          += $locationOrgStock->quantity * $orgStock->unit_value;
            $orgStockCommercialValue += $locationOrgStock->quantity * $orgStock->unit_commercial_value;
        }


        $location->update([
            'stock_value'            => $orgStockValue,
            'stock_commercial_value' => $orgStockCommercialValue
        ]);

    }

}
